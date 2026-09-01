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
use Illuminate\Support\Facades\Http;
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

    public function test_one_button_registers_the_learner_and_issues_the_payment_reference(): void
    {
        $application = $this->pendingApplication();
        $this->fakePayAt();

        Livewire::test(ListApplications::class)
            ->callTableAction('registerAndSend', $application)
            ->assertHasNoTableActionErrors();

        $enrolment = Enrolment::where('application_id', $application->id)->firstOrFail();

        // The whole point: the registrar pressed one button and the learner
        // now has a payable reference, rather than sitting at awaiting_payment
        // while nobody realises two more screens were needed.
        $this->assertNotNull($enrolment->activatingInvoice()->fresh()->payat_source_reference);

        $this->assertSame(EnrolmentStatus::PENDING, $enrolment->status);
        $this->assertSame(ApplicationStatus::AWAITING_PAYMENT, $application->fresh()->status);

        // R500 registration, then R950 three times — read off the offering,
        // never chosen by whoever pressed the button.
        $this->assertCount(4, $enrolment->invoices);
        $this->assertSame(335000, $enrolment->invoices->sum('amount_cents'));
        $this->assertSame(50000, $enrolment->activatingInvoice()->amount_cents);
    }

    public function test_an_application_cannot_be_registered_a_second_time(): void
    {
        $application = $this->pendingApplication();
        $this->fakePayAt();

        Livewire::test(ListApplications::class)
            ->callTableAction('registerAndSend', $application);

        // Double-invoicing a learner is prevented by the button disappearing
        // once the decision is made, not by hoping nobody clicks twice.
        Livewire::test(ListApplications::class)
            ->assertTableActionHidden('registerAndSend', $application->fresh())
            ->assertTableActionHidden('accept', $application->fresh());

        $this->assertSame(1, Enrolment::count());
        $this->assertSame(335000, Enrolment::first()->invoices->sum('amount_cents'));
    }

    public function test_the_offering_is_not_a_question_when_only_one_is_open(): void
    {
        $application = $this->pendingApplication();

        // Every 2027 programme is sold one way, so asking a registrar to pick
        // "the offering" was internal vocabulary posing as a decision.
        Livewire::test(ListApplications::class)
            ->assertTableActionVisible('registerAndSend', $application)
            ->assertTableActionHidden('accept', $application);
    }

    public function test_a_real_choice_of_offering_is_still_asked(): void
    {
        $application = $this->pendingApplication();

        // A second open offering on the same programme makes the price a
        // genuine decision, so the picker comes back.
        $original = Offering::where('code', 'PPO-2027')->firstOrFail();
        $second = $original->replicate();
        $second->code = 'PPO-2027-EVENING';
        $second->name = $original->name.' (evening)';
        $second->save();

        Livewire::test(ListApplications::class)
            ->assertTableActionHidden('registerAndSend', $application)
            ->assertTableActionVisible('accept', $application);
    }

    public function test_a_draft_offering_cannot_be_sold_through_the_dashboard(): void
    {
        $application = $this->pendingApplication();

        $offering = Offering::where('code', 'PPO-2027')->firstOrFail();
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
        $offering = Offering::where('code', 'PPO-2027')->firstOrFail();

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

    /** Pay@ answers as the live account does, without touching it. */
    private function fakePayAt(): void
    {
        config(['payat.client_id' => 'client-test', 'payat.client_secret' => 'secret-test']);

        Http::fake([
            'https://go.payat.co.za/yapi/oauth/token' => Http::response([
                'access_token' => 'tok-1', 'expires_in' => 3599,
            ]),
            'https://go.payat.co.za/yapi/v1/merchant/rtp/create/single' => Http::response([
                'clientAccountNumber' => '9000000010',
                'sourceReference' => '117009955499000000010',
                'paymentLink' => 'https://payat.io/qr/117009955499000000010',
                'amount' => 50000,
                'amountPaid' => 0,
                'accountState' => 'PAYMENT_OUTSTANDING',
            ]),
        ]);
    }
}
