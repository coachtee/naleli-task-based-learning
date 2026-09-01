<?php

declare(strict_types=1);

namespace Tests\Feature\Leads;

use App\Enums\ApplicationSource;
use App\Enums\ApplicationStatus;
use App\Enums\TouchChannel;
use App\Enums\TouchOutcome;
use App\Models\Application;
use App\Models\Invoice;
use App\Models\Learner;
use App\Services\Identity\LearnerRegistry;
use App\Services\Leads\MetaLeadImporter;
use App\Services\Leads\TouchLog;
use Database\Seeders\ProgrammeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Eighty-five names off a Facebook ad, turned into admissions work.
 *
 * The export is the awkward part: Meta's columns change with the form, a
 * custom question becomes its own column, Excel adds a byte-order mark, and
 * phone numbers arrive as "p:+27821234567". So most of what is asserted here
 * is the importer refusing to be confused by a real file.
 */
class MetaLeadImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ProgrammeSeeder::class);
    }

    public function test_an_export_becomes_leads_nobody_has_called_yet(): void
    {
        $result = $this->import($this->metaExport());

        $this->assertSame(3, $result['imported']);
        $this->assertSame([], $result['skipped']);
        $this->assertSame('KCS Aug 2026 — Digital Foundation', $result['campaign']);

        $lead = Application::where('source_reference', 'l_1001')->firstOrFail();

        $this->assertSame(ApplicationStatus::LEAD, $lead->status, 'they enquired, they did not register');
        $this->assertSame(ApplicationSource::META_LEAD, $lead->source);
        $this->assertSame('KCS Aug 2026 — Digital Foundation', $lead->campaign);
        $this->assertNotNull($lead->next_action_at, 'due now — they have been waiting since the ad ran');
        $this->assertSame(0, $lead->touch_count);
        // Filed under the Foundation: the catalogue says everyone starts
        // there, and a lead who has not spoken to anybody has not chosen a
        // specialisation either.
        $this->assertSame('DOPF', $lead->programme->code);

        // A name that arrives as one field is split so a caller can greet them.
        $this->assertSame('Mpho', $lead->learner->first_name);
        $this->assertSame('Moleko', $lead->learner->last_name);
        // "p:" stripped, normalised to something dialable.
        $this->assertStringContainsString('82', (string) $lead->learner->phone);
    }

    public function test_importing_leads_never_bills_anybody(): void
    {
        $this->import($this->metaExport());

        // The whole reason these are LEAD and not registrations. An invoice
        // for somebody who only asked the price is a debt they never agreed to.
        $this->assertSame(0, Invoice::count());
        $this->assertSame(3, Learner::count());
    }

    public function test_the_same_file_imported_twice_changes_nothing(): void
    {
        $first = $this->import($this->metaExport());
        $second = $this->import($this->metaExport());

        $this->assertSame(3, $first['imported']);
        $this->assertSame(0, $second['imported']);
        $this->assertSame(3, $second['duplicates']);
        $this->assertSame(3, Application::count());
    }

    public function test_a_lead_who_later_registers_is_the_same_person(): void
    {
        $this->import($this->metaExport());
        $lead = Application::where('source_reference', 'l_1001')->firstOrFail();

        // Six weeks later she fills in the form on the website herself. The
        // registry matches her on email, so the Facebook lead is still hers
        // rather than becoming a second, unrelated record.
        $sameLearner = app(LearnerRegistry::class)->resolve([
            'first_name' => 'Mpho', 'last_name' => 'Moleko',
            'email' => 'mpho.moleko@example.co.za', 'phone' => '082 555 0101',
        ]);

        $this->assertSame($lead->learner_id, $sameLearner->id);
        $this->assertSame(3, Learner::count(), 'no duplicate person was created');
    }

    public function test_a_row_with_no_way_to_reach_them_is_reported_not_swallowed(): void
    {
        $csv = "id,full_name,email,phone_number\n"
             ."l_2001,No Contact,,\n"
             ."l_2002,,ghost@example.co.za,\n"
             ."l_2003,Real Person,real@example.co.za,+27825550199\n";

        $result = $this->import($csv);

        $this->assertSame(1, $result['imported']);
        $this->assertCount(2, $result['skipped']);
        $this->assertStringContainsString('no email and no phone', $result['skipped'][0]);
        $this->assertStringContainsString('no name', $result['skipped'][1]);
    }

    public function test_a_file_with_a_byte_order_mark_and_odd_columns_still_imports(): void
    {
        // Excel round-trips a Meta export and this is what comes back out.
        $csv = "\xEF\xBB\xBF"."Id,Created Time,Full Name,Email Address,Phone Number,Ad Name\n"
             .'l_3001,2026-08-30T09:12:00+0200,Thulas Mbokane,thulas@example.co.za,p:+27825550102,"Winter push"'."\n";

        $result = $this->import($csv);

        $this->assertSame(1, $result['imported']);
        $lead = Application::firstOrFail();
        $this->assertSame('Winter push', $lead->campaign);
        $this->assertSame('thulas@example.co.za', $lead->learner->email);
        $this->assertSame('2026-08-30', $lead->applied_at->format('Y-m-d'));
    }

    public function test_a_file_that_is_not_a_lead_export_says_so(): void
    {
        $this->expectExceptionMessageMatches('/No email or phone column found/');
        $this->import("date,amount,reference\n2026-08-30,500,ABC123\n");
    }

    // ------------------------------------------------------- the touch log

    public function test_calling_a_lead_moves_it_on_and_schedules_the_next_call(): void
    {
        $this->import($this->metaExport());
        $lead = Application::where('source_reference', 'l_1001')->firstOrFail();

        app(TouchLog::class)->record(
            $lead, TouchChannel::PHONE, TouchOutcome::NO_ANSWER, 'Rang out, will try after 5.',
        );

        $lead->refresh();
        $this->assertSame(ApplicationStatus::CONTACTED, $lead->status);
        $this->assertSame(1, $lead->touch_count);
        $this->assertNotNull($lead->first_contacted_at);
        // Two days out. Compared by day rather than by a float difference,
        // which lands a hair under 2.0 and truncates to 1.
        $this->assertTrue($lead->next_action_at->isSameDay(now()->addDays(2)));

        app(TouchLog::class)->record($lead, TouchChannel::PHONE, TouchOutcome::SPOKE, 'Wants the info.');

        $lead->refresh();
        $this->assertSame(2, $lead->touch_count);
        // The sequence is the point: two attempts, one of which connected.
        $this->assertSame(
            ['spoke', 'no_answer'],
            $lead->leadTouches()->get()->pluck('outcome.value')->all(),
        );
    }

    public function test_a_dead_lead_leaves_the_queue_instead_of_being_skipped_forever(): void
    {
        $this->import($this->metaExport());
        $lead = Application::where('source_reference', 'l_1001')->firstOrFail();

        app(TouchLog::class)->record($lead, TouchChannel::PHONE, TouchOutcome::NOT_INTERESTED, 'Found work.');

        $lead->refresh();
        $this->assertNull($lead->next_action_at, 'nothing to come back to');
        $this->assertSame(ApplicationStatus::WITHDRAWN, $lead->status);
    }

    public function test_a_later_call_cannot_drag_a_registered_learner_backwards(): void
    {
        $this->import($this->metaExport());
        $lead = Application::where('source_reference', 'l_1001')->firstOrFail();
        $lead->update(['status' => ApplicationStatus::PAID]);

        app(TouchLog::class)->record($lead, TouchChannel::PHONE, TouchOutcome::NOT_INTERESTED, 'Misdial.');

        $this->assertSame(ApplicationStatus::PAID, $lead->fresh()->status);
    }

    // ------------------------------------------------------------- helpers

    /** @return array{imported: int, duplicates: int, skipped: array<int, string>, campaign: ?string} */
    private function import(string $csv): array
    {
        $handle = fopen('php://memory', 'r+');
        fwrite($handle, $csv);
        rewind($handle);

        try {
            return app(MetaLeadImporter::class)->importStream($handle);
        } finally {
            fclose($handle);
        }
    }

    /** The shape Meta's Leads Center actually exports. */
    private function metaExport(): string
    {
        $header = 'id,created_time,ad_id,ad_name,adset_name,campaign_name,form_id,form_name,'
                .'is_organic,platform,full_name,email,phone_number';

        $rows = [
            'l_1001,2026-08-30T07:41:00+0200,120210,"KCS Aug 2026 — Digital Foundation","Katlehong 18-35",'
            .'"Get leads",900,"Apply now",false,fb,Mpho Moleko,mpho.moleko@example.co.za,p:+27825550101',

            'l_1002,2026-08-30T08:02:00+0200,120210,"KCS Aug 2026 — Digital Foundation","Katlehong 18-35",'
            .'"Get leads",900,"Apply now",false,ig,Nonhlanhla Warona Mashego,warona@example.co.za,p:+27825550102',

            'l_1003,2026-08-31T19:15:00+0200,120210,"KCS Aug 2026 — Digital Foundation","Katlehong 18-35",'
            .'"Get leads",900,"Apply now",false,fb,Refiloe Fana,refiloe.fana@example.co.za,p:+27825550103',
        ];

        return $header."\n".implode("\n", $rows)."\n";
    }
}
