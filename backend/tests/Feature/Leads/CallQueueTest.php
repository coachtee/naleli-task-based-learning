<?php

declare(strict_types=1);

namespace Tests\Feature\Leads;

use App\Enums\ApplicationSource;
use App\Enums\ApplicationStatus;
use App\Enums\TouchChannel;
use App\Enums\TouchOutcome;
use App\Enums\UserRole;
use App\Filament\Widgets\CallQueue;
use App\Models\Application;
use App\Models\Learner;
use App\Models\Programme;
use App\Models\User;
use App\Services\Leads\TouchLog;
use Database\Seeders\ProgrammeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The queue a registrar opens in the morning.
 *
 * Widgets render lazily over Livewire, so a broken column formatter only
 * surfaces when somebody actually looks at it — which is why the queue is
 * mounted here rather than assumed to work because the dashboard returned 200.
 */
class CallQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ProgrammeSeeder::class);
        $this->actingAs(User::factory()->create(['role' => UserRole::ADMIN]));
    }

    public function test_the_queue_shows_who_is_waiting_longest_first(): void
    {
        $urgent = $this->lead('Mpho', 'Moleko', now()->subDays(6));
        $today = $this->lead('Sipho', 'Dube', now());
        $later = $this->lead('Lerato', 'Nkosi', now()->addDays(4));

        Livewire::test(CallQueue::class)
            ->assertCanSeeTableRecords([$urgent, $today, $later], inOrder: true)
            ->assertSee('Mpho')
            ->assertSee('never called');
    }

    public function test_a_closed_lead_leaves_the_queue_instead_of_being_skipped(): void
    {
        $live = $this->lead('Mpho', 'Moleko', now()->subDay());
        $dead = $this->lead('Thulas', 'Mbokane', now()->subDay());

        app(TouchLog::class)->record($dead, TouchChannel::PHONE, TouchOutcome::NOT_INTERESTED, 'Found work.');

        Livewire::test(CallQueue::class)
            ->assertCanSeeTableRecords([$live])
            ->assertCanNotSeeTableRecords([$dead]);
    }

    public function test_logging_a_call_records_it_and_reschedules_them(): void
    {
        $lead = $this->lead('Mpho', 'Moleko', now()->subDays(2));

        Livewire::test(CallQueue::class)
            ->callTableAction('logCall', $lead, [
                'channel' => TouchChannel::PHONE->value,
                'outcome' => TouchOutcome::SPOKE->value,
                'note' => 'Wants to start in February. Send her the fees.',
            ])
            ->assertHasNoTableActionErrors();

        $lead->refresh();

        $this->assertSame(ApplicationStatus::CONTACTED, $lead->status);
        $this->assertSame(1, $lead->touch_count);
        $this->assertTrue($lead->next_action_at->isSameDay(now()->addDays(3)));

        $touch = $lead->leadTouches()->firstOrFail();
        $this->assertSame(TouchOutcome::SPOKE, $touch->outcome);
        $this->assertStringContainsString('February', (string) $touch->note);
        // Who made the call, so the next person knows who to ask.
        $this->assertNotNull($touch->user_id);
    }

    public function test_a_chosen_call_back_date_beats_the_default_interval(): void
    {
        $lead = $this->lead('Mpho', 'Moleko', now());

        Livewire::test(CallQueue::class)
            ->callTableAction('logCall', $lead, [
                'channel' => TouchChannel::PHONE->value,
                'outcome' => TouchOutcome::SPOKE->value,
                'next_action_at' => now()->addDays(14)->toDateString(),
            ])
            ->assertHasNoTableActionErrors();

        // "Call me after payday" must win over the three-day default.
        $this->assertTrue($lead->fresh()->next_action_at->isSameDay(now()->addDays(14)));
    }

    public function test_a_lead_with_no_number_is_not_offered_whatsapp(): void
    {
        $reachable = $this->lead('Mpho', 'Moleko', now(), phone: '082 555 0101');
        $emailOnly = $this->lead('Thulas', 'Mbokane', now(), phone: null);

        Livewire::test(CallQueue::class)
            ->assertTableActionVisible('whatsapp', $reachable)
            ->assertTableActionHidden('whatsapp', $emailOnly);
    }

    public function test_a_csv_uploaded_from_the_dashboard_fills_the_queue(): void
    {
        $csv = "id,created_time,ad_name,full_name,email,phone_number\n"
             ."l_9001,2026-08-30T07:41:00+0200,\"KCS Aug 2026\",Mpho Moleko,mpho@example.co.za,p:+27825550101\n"
             ."l_9002,2026-08-30T08:02:00+0200,\"KCS Aug 2026\",Refiloe Fana,refiloe@example.co.za,p:+27825550102\n";

        Livewire::test(CallQueue::class)
            ->callTableAction('importLeads', null, [
                'file' => UploadedFile::fake()
                    ->createWithContent('leads.csv', $csv),
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame(2, Application::where('status', ApplicationStatus::LEAD)->count());

        $lead = Application::where('source_reference', 'l_9001')->firstOrFail();
        $this->assertSame('KCS Aug 2026', $lead->campaign);
        $this->assertNotNull($lead->next_action_at, 'imported leads are due immediately');

        // And they are actually in the queue a registrar looks at.
        Livewire::test(CallQueue::class)->assertSee('Mpho')->assertSee('Refiloe');
    }

    public function test_uploading_the_wrong_file_says_so_instead_of_failing_silently(): void
    {
        Livewire::test(CallQueue::class)
            ->callTableAction('importLeads', null, [
                'file' => UploadedFile::fake()
                    ->createWithContent('bank.csv', "date,amount\n2026-08-30,500\n"),
            ])
            ->assertHasNoTableActionErrors();

        // The action reports rather than throws: a registrar who picked the
        // wrong file needs to be told, not shown a stack trace.
        $this->assertSame(0, Application::count());
    }

    // ------------------------------------------------------------- helpers

    private function lead(
        string $first,
        string $last,
        Carbon $due,
        ?string $phone = '082 555 0100',
    ): Application {
        $learner = Learner::create([
            'learner_ref' => 'NAL-2026-'.str_pad((string) (Learner::count() + 1), 5, '0', STR_PAD_LEFT),
            'first_registered_year' => 2026,
            'first_name' => $first,
            'last_name' => $last,
            'email' => strtolower($first).'@example.co.za',
            'phone' => $phone,
            'whatsapp' => $phone,
        ]);

        return Application::create([
            'learner_id' => $learner->id,
            'programme_id' => Programme::where('code', 'DOPF')->value('id'),
            'status' => ApplicationStatus::LEAD,
            'source' => ApplicationSource::META_LEAD,
            'source_reference' => 'l_'.$learner->id,
            'campaign' => 'KCS Aug 2026',
            'applied_at' => now()->subWeek(),
            'next_action_at' => $due,
        ]);
    }
}
