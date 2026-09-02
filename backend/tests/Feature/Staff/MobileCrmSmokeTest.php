<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use App\Enums\ApplicationSource;
use App\Enums\ApplicationStatus;
use App\Enums\InvoiceStatus;
use App\Enums\UserRole;
use App\Models\Application;
use App\Models\Enrolment;
use App\Models\Invoice;
use App\Models\Learner;
use App\Models\Programme;
use App\Models\User;
use Database\Seeders\ProgrammeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every screen in the approved mobile CRM redesign, checked for the thing a
 * unit test cannot: that the Blade actually compiles and renders end to end
 * for a real, logged-in staff member — not just that a controller method
 * returns the right array.
 */
class MobileCrmSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ProgrammeSeeder::class);
        $this->actingAs(User::factory()->create(['role' => UserRole::ADMIN, 'name' => 'Bruce Masingue']));
    }

    public function test_the_dashboard_renders_with_real_data(): void
    {
        $this->lead('Mpho', 'Moleko');

        $this->get('/staff')->assertOk()->assertSee('Dashboard', false)->assertSee('Mpho Moleko', false);
    }

    public function test_the_dashboard_renders_with_nothing_in_the_queue(): void
    {
        $this->get('/staff')->assertOk()->assertSee('Nobody is waiting', false);
    }

    public function test_records_registrations_tab_renders(): void
    {
        $learner = $this->registeredLearner();
        Application::create([
            'learner_id' => $learner->id,
            'programme_id' => Programme::where('code', 'DOPF')->value('id'),
            'status' => ApplicationStatus::AWAITING_PAYMENT,
            'source' => ApplicationSource::FLUENTFORM,
            'applied_at' => now(),
        ]);

        $this->get('/records')->assertOk()->assertSee('Registrations', false)->assertSee($learner->full_name, false);
    }

    public function test_records_learners_tab_renders(): void
    {
        $learner = $this->registeredLearner();

        $this->get('/records?tab=learners')->assertOk()->assertSee($learner->full_name, false);
    }

    public function test_records_invoices_tab_renders(): void
    {
        $learner = $this->registeredLearner();
        $enrolment = Enrolment::create([
            'learner_id' => $learner->id,
            'programme_id' => Programme::where('code', 'DOPF')->value('id'),
            'status' => 'active',
        ]);
        Invoice::create([
            'learner_id' => $learner->id,
            'enrolment_id' => $enrolment->id,
            'sequence' => 1,
            'description' => 'Registration fee',
            'amount_cents' => 50000,
            'status' => InvoiceStatus::DUE,
            'due_on' => now()->addWeek(),
        ]);

        $this->get('/records?tab=invoices')->assertOk()->assertSee('Registration fee', false)->assertSee('R500.00', false);
    }

    public function test_a_learner_profile_renders(): void
    {
        $learner = $this->registeredLearner();

        $this->get("/records/learners/{$learner->id}")->assertOk()->assertSee($learner->full_name, false);
    }

    public function test_a_lead_profile_renders(): void
    {
        $application = $this->lead('Mpho', 'Moleko');

        $this->get("/calls/{$application->id}")->assertOk()->assertSee('Mpho Moleko', false);
    }

    public function test_the_more_menu_renders(): void
    {
        $this->get('/more')->assertOk()->assertSee('Bruce Masingue', false);
    }

    // ------------------------------------------------------------- helpers

    private function lead(string $first, string $last): Application
    {
        $learner = Learner::create([
            'learner_ref' => 'NAL-2026-'.str_pad((string) (Learner::count() + 1), 5, '0', STR_PAD_LEFT),
            'first_registered_year' => 2026,
            'first_name' => $first,
            'last_name' => $last,
            'email' => strtolower($first).'@example.co.za',
            'phone' => '082 555 0100',
            'whatsapp' => '082 555 0100',
        ]);

        return Application::create([
            'learner_id' => $learner->id,
            'programme_id' => Programme::where('code', 'DOPF')->value('id'),
            'status' => ApplicationStatus::LEAD,
            'source' => ApplicationSource::META_LEAD,
            'source_reference' => 'l_'.$learner->id,
            'campaign' => 'KCS Aug 2026',
            'applied_at' => now()->subWeek(),
            'next_action_at' => now(),
        ]);
    }

    private function registeredLearner(): Learner
    {
        return Learner::create([
            'learner_ref' => 'NAL-2026-'.str_pad((string) (Learner::count() + 1), 5, '0', STR_PAD_LEFT),
            'first_registered_year' => 2026,
            'first_name' => 'Palesa',
            'last_name' => 'Mahlangu',
            'email' => 'palesa@example.co.za',
            'phone' => '082 555 0134',
            'whatsapp' => '082 555 0134',
        ]);
    }
}
