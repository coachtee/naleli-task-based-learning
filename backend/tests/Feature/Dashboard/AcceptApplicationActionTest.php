<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

use App\Enums\ApplicationSource;
use App\Enums\ApplicationStatus;
use App\Enums\EnrolmentStatus;
use App\Enums\LearnerStatus;
use App\Enums\OfferingStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Applications\Pages\ListApplications;
use App\Models\Application;
use App\Models\Enrolment;
use App\Models\Learner;
use App\Models\Offering;
use App\Models\User;
use Database\Seeders\ProgrammeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Accepting an application is the one admissions decision the dashboard
 * exists to make, and it is the point where money is committed. The service
 * behind it is covered by the end-to-end flow test; what is asserted here is
 * the wiring — that the button a registrar actually presses reaches that
 * service, and that a draft offering cannot be sold through it.
 */
class AcceptApplicationActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ProgrammeSeeder::class);
        $this->actingAs(User::factory()->create(['role' => UserRole::REGISTRAR]));
    }

    public function test_a_registrar_accepts_an_application_and_the_offering_decides_the_invoices(): void
    {
        $application = $this->pendingApplication();
        $offering = Offering::where('code', 'PPO-2026')->firstOrFail();

        Livewire::test(ListApplications::class)
            ->callTableAction('accept', $application, ['offering_id' => $offering->id])
            ->assertHasNoTableActionErrors();

        $enrolment = Enrolment::where('application_id', $application->id)->firstOrFail();

        $this->assertSame(EnrolmentStatus::PENDING, $enrolment->status);
        $this->assertSame(ApplicationStatus::AWAITING_PAYMENT, $application->fresh()->status);

        // R500 registration, then R950 three times — read off the offering,
        // never chosen by whoever pressed the button.
        $this->assertCount(4, $enrolment->invoices);
        $this->assertSame(335000, $enrolment->invoices->sum('amount_cents'));
        $this->assertSame(50000, $enrolment->activatingInvoice()->amount_cents);
    }

    public function test_an_application_cannot_be_accepted_a_second_time(): void
    {
        $application = $this->pendingApplication();
        $offering = Offering::where('code', 'PPO-2026')->firstOrFail();

        Livewire::test(ListApplications::class)
            ->callTableAction('accept', $application, ['offering_id' => $offering->id]);

        // Double-invoicing a learner is prevented by the button disappearing
        // once the decision is made, not by hoping nobody clicks twice.
        Livewire::test(ListApplications::class)
            ->assertTableActionHidden('accept', $application->fresh());

        $this->assertSame(1, Enrolment::count());
        $this->assertSame(335000, Enrolment::first()->invoices->sum('amount_cents'));
    }

    public function test_a_draft_offering_cannot_be_sold_through_the_dashboard(): void
    {
        $application = $this->pendingApplication();

        $offering = Offering::where('code', 'PPO-2026')->firstOrFail();
        $offering->update(['status' => OfferingStatus::DRAFT]);

        Livewire::test(ListApplications::class)
            ->callTableAction('accept', $application, ['offering_id' => $offering->id]);

        // The refusal is reported, not thrown at the registrar as a 500, and
        // nothing is committed.
        $this->assertSame(0, Enrolment::count());
        $this->assertSame(ApplicationStatus::REGISTRATION_STARTED, $application->fresh()->status);
    }

    private function pendingApplication(): Application
    {
        $offering = Offering::where('code', 'PPO-2026')->firstOrFail();

        $learner = Learner::create([
            'learner_ref' => 'NAL-2026-09001',
            'first_registered_year' => 2026,
            'first_name' => 'Thandi',
            'last_name' => 'Mokoena',
            'email' => 'thandi.mokoena@example.co.za',
            'status' => LearnerStatus::APPLICANT,
        ]);

        return Application::create([
            'learner_id' => $learner->id,
            'programme_id' => $offering->programme_id,
            'intake_id' => $offering->intake_id,
            'status' => ApplicationStatus::REGISTRATION_STARTED,
            'source' => ApplicationSource::FLUENTFORM,
            'applied_at' => now()->subDays(3),
        ]);
    }
}
