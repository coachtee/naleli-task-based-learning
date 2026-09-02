<?php

declare(strict_types=1);

namespace Tests\Feature\Leads;

use App\Enums\ApplicationSource;
use App\Enums\ApplicationStatus;
use App\Enums\TouchChannel;
use App\Enums\TouchOutcome;
use App\Enums\UserRole;
use App\Models\Application;
use App\Models\Learner;
use App\Models\Programme;
use App\Models\User;
use App\Services\Leads\TouchLog;
use Database\Seeders\ProgrammeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The mobile call queue — same data as the Filament widget, served as JSON
 * to a page built for a phone instead of a desk.
 *
 * The Filament table hit a real ceiling on a narrow screen: even after
 * hiding columns and shrinking the action buttons to icons, the row still
 * ran wider than the viewport, because a data table's columns each claim
 * their own width and a phone does not have enough of it to give away. This
 * page is a card feed instead, and what is asserted here is that the same
 * underlying rules — no bulk WhatsApp beyond one tap at a time, the outcome
 * sets the next call date, competence stays out of reach — hold on this
 * surface exactly as they do on the desktop one.
 */
class MobileCallQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ProgrammeSeeder::class);
        $this->actingAs(User::factory()->create(['role' => UserRole::ADMIN, 'name' => 'Bruce Masingue']));
    }

    public function test_the_page_requires_a_login(): void
    {
        auth()->logout();

        $this->get('/calls')->assertRedirect();
        $this->getJson('/calls/api/leads')->assertUnauthorized();
    }

    public function test_the_page_loads_for_a_logged_in_staff_member(): void
    {
        $this->get('/calls')->assertOk()->assertSee('Calls', false);
    }

    public function test_the_feed_lists_leads_oldest_waiting_first(): void
    {
        $old = $this->lead('Mpho', 'Moleko', now()->subDays(5));
        $new = $this->lead('Sipho', 'Dube', now());

        $response = $this->getJson('/calls/api/leads')->assertOk();
        $ids = collect($response->json('leads'))->pluck('id')->all();

        $this->assertSame([$old->id, $new->id], $ids);
    }

    public function test_an_untouched_lead_four_days_overdue_shows_as_urgent(): void
    {
        $this->lead('Mpho', 'Moleko', now()->subDays(4));

        $row = $this->getJson('/calls/api/leads')->json('leads.0');

        $this->assertSame('Mpho Moleko', $row['name']);
        $this->assertSame(0, $row['touch_count']);
        $this->assertTrue($row['overdue']);
        $this->assertTrue($row['urgent'], 'more than 3 days waiting');
        $this->assertTrue($row['can_whatsapp']);
    }

    public function test_the_card_shows_what_the_last_call_actually_said(): void
    {
        $lead = $this->lead('Mpho', 'Moleko', now()->subDays(4));
        app(TouchLog::class)->record($lead, TouchChannel::PHONE, TouchOutcome::NO_ANSWER, 'Rang out.');

        $row = $this->getJson('/calls/api/leads')->json('leads.0');

        $this->assertSame(1, $row['touch_count']);
        $this->assertSame('no_answer', $row['last_outcome']);
        $this->assertSame('Rang out.', $row['last_note']);
        // A no-answer reschedules two days out — the card must reflect that
        // this lead is no longer the urgent one it was before the call.
        $this->assertFalse($row['overdue'], 'logging a touch moves it forward, not back');
    }

    public function test_tapping_whatsapp_opens_the_chat_and_logs_it_as_one_touch(): void
    {
        $lead = $this->lead('Mpho', 'Moleko', now());

        $response = $this->postJson("/calls/api/leads/{$lead->id}/whatsapp")->assertOk();

        $this->assertStringStartsWith('https://wa.me/', $response->json('url'));
        $this->assertSame(1, $lead->fresh()->touch_count);
        $this->assertSame(TouchOutcome::SENT_INFO, $lead->leadTouches()->first()->outcome);
    }

    public function test_a_lead_with_no_number_cannot_be_whatsapped(): void
    {
        $lead = $this->lead('Mpho', 'Moleko', now(), phone: null);

        $this->postJson("/calls/api/leads/{$lead->id}/whatsapp")
            ->assertStatus(422);

        $this->assertSame(0, $lead->fresh()->touch_count, 'a failed attempt is not logged as a touch');
    }

    public function test_logging_a_call_reschedules_and_shows_up_on_the_next_load(): void
    {
        $lead = $this->lead('Mpho', 'Moleko', now()->subDays(2));

        $this->postJson("/calls/api/leads/{$lead->id}/log", [
            'channel' => 'phone',
            'outcome' => 'spoke',
            'note' => 'Wants to start in February.',
        ])->assertOk()->assertJsonPath('lead.last_outcome', 'spoke');

        $lead->refresh();
        $this->assertSame(ApplicationStatus::CONTACTED, $lead->status);
        $this->assertTrue($lead->next_action_at->isSameDay(now()->addDays(3)));

        $touch = $lead->leadTouches()->firstOrFail();
        $this->assertSame('Wants to start in February.', $touch->note);
        $this->assertSame($this->auth()->id, $touch->user_id, 'the caller is recorded, not anonymous');
    }

    public function test_not_interested_removes_them_from_the_feed(): void
    {
        $stays = $this->lead('Mpho', 'Moleko', now());
        $leaves = $this->lead('Thulas', 'Mbokane', now());

        $this->postJson("/calls/api/leads/{$leaves->id}/log", [
            'channel' => 'phone', 'outcome' => 'not_interested', 'note' => 'Found work.',
        ])->assertOk();

        $ids = collect($this->getJson('/calls/api/leads')->json('leads'))->pluck('id')->all();
        $this->assertSame([$stays->id], $ids);
    }

    public function test_a_client_cannot_send_a_made_up_outcome(): void
    {
        $lead = $this->lead('Mpho', 'Moleko', now());

        $this->postJson("/calls/api/leads/{$lead->id}/log", [
            'channel' => 'phone', 'outcome' => 'definitely_enrolling_trust_me',
        ])->assertStatus(500);

        $this->assertSame(0, $lead->fresh()->touch_count);
    }

    public function test_a_csv_can_be_imported_from_the_phone(): void
    {
        $csv = "id,created_time,ad_name,full_name,email,phone_number\n"
             ."l_7001,2026-08-30T07:41:00+0200,\"Mobile import\",Mpho Moleko,mpho@example.co.za,p:+27825550101\n";

        $response = $this->post('/calls/api/import', [
            'file' => UploadedFile::fake()->createWithContent('leads.csv', $csv),
        ], ['Accept' => 'application/json'])->assertOk();

        $this->assertSame(1, $response->json('imported'));
        $this->assertSame(1, Application::where('source_reference', 'l_7001')->count());
    }

    // ------------------------------------------------------------- helpers

    private function auth(): User
    {
        return auth()->user();
    }

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
