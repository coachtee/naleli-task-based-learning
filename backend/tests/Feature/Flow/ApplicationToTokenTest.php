<?php

declare(strict_types=1);

namespace Tests\Feature\Flow;

use App\Enums\ApplicationStatus;
use App\Enums\BillingModel;
use App\Enums\EnrolmentStatus;
use App\Enums\EntitlementState;
use App\Enums\InvoiceStatus;
use App\Enums\LearnerStatus;
use App\Enums\OfferingStatus;
use App\Enums\PaymentStatus;
use App\Enums\TokenStatus;
use App\Models\AccessToken;
use App\Models\Application;
use App\Models\Enrolment;
use App\Models\Learner;
use App\Models\Offering;
use App\Models\Payment;
use App\Services\Enrolment\ApplicationAcceptor;
use App\Services\Enrolment\EnrolmentActivator;
use App\Services\Registration\ProfileCompleteness;
use App\Services\Tokens\AccessTokenIssuer;
use Database\Seeders\ProgrammeSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The Phase 1 gate, end to end:
 *
 *   Submit application → learner created → payment confirmed
 *   → enrolment activated → token generated → app activates with it
 *
 * Everything the backend exists to prove in this phase is asserted here. No
 * payment gateway is involved and the Android app is not touched — the token
 * is redeemed through the API the app will eventually call.
 */
class ApplicationToTokenTest extends TestCase
{
    use RefreshDatabase;

    private const VALID_SA_ID = '9001015800088';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ProgrammeSeeder::class);
        config(['webhooks.fluentform.secret' => 'test-secret']);
    }

    public function test_the_whole_journey_from_website_form_to_an_activated_app(): void
    {
        // 1 — the learner completes the existing Student Application Form.
        $response = $this->postSignedApplication($this->formEightPayload());

        $response->assertCreated()->assertJson(['status' => 'created']);

        $learnerRef = $response->json('learner_ref');
        $this->assertMatchesRegularExpression('/^NAL-\d{4}-\d{5}$/', $learnerRef);

        $learner = Learner::where('learner_ref', $learnerRef)->firstOrFail();
        $application = Application::firstOrFail();

        $this->assertSame(ApplicationStatus::REGISTRATION_STARTED, $application->status);
        $this->assertSame('PPO', $application->programme->code);
        $this->assertSame('February 2027', $application->intake->label);
        // The SA ID validated itself, so identity is already established.
        $this->assertTrue($learner->hasVerifiedIdentity());

        // 2 — the registrar accepts against the offering. The invoices are
        //     derived from the product's billing model, so the shape cannot
        //     drift from what the school actually sells.
        $enrolment = app(ApplicationAcceptor::class)->accept($application, $this->careerModuleOffering());

        $this->assertSame(EnrolmentStatus::PENDING, $enrolment->status);
        $this->assertSame(ApplicationStatus::AWAITING_PAYMENT, $application->fresh()->status);

        // R500 registration + R950 x 3.
        $this->assertCount(4, $enrolment->invoices);
        $this->assertSame(335000, $enrolment->invoices->sum('amount_cents'));

        // 3 — payment confirmed by hand, exactly as Filament does it. Pay@ Go
        //     because that is what a learner without a card actually uses.
        $activating = $enrolment->activatingInvoice();
        $this->assertSame(50000, $activating->amount_cents, 'the registration fee opens access');

        $result = app(EnrolmentActivator::class)->confirmInvoiceManually(
            invoice: $activating,
            providerKey: 'payat_go',
            reference: 'PAYAT-20270114-0001',
        );

        // 4 — enrolment activated, entitlement opened, token issued.
        $this->assertFalse($result['already_settled']);
        $this->assertSame(PaymentStatus::SETTLED, $result['payment']->status);
        $this->assertSame(InvoiceStatus::PAID, $activating->fresh()->status);
        $this->assertSame(EnrolmentStatus::ACTIVE, $enrolment->fresh()->status);
        $this->assertSame(LearnerStatus::ACTIVE, $learner->fresh()->status);
        // Paid, studying, and still owing us the rest of the profile — which
        // is the whole point of collecting it after the money rather than
        // before it. The token was issued anyway; only identity gates that.
        $this->assertSame(ApplicationStatus::PROFILE_INCOMPLETE, $application->fresh()->status);
        $this->assertContains('Home address', app(ProfileCompleteness::class)->missing($learner->fresh()));

        $entitlement = $learner->entitlements()
            ->where('programme_id', $enrolment->programme_id)
            ->firstOrFail();
        $this->assertSame(EntitlementState::ACTIVE, $entitlement->state);

        $plain = $result['plain_token'];
        $this->assertNotNull($plain);
        $this->assertMatchesRegularExpression('/^KCS-[0-9A-Z]{4}-[0-9A-Z]{4}-[0-9A-Z]{4}$/', $plain);

        // The plain value is never stored — only its hash.
        $this->assertDatabaseMissing('access_tokens', ['token_prefix' => $plain]);

        // The three monthly instalments stay owing: settling one invoice
        // settles one invoice, and access is already open.
        $this->assertSame(3, $enrolment->invoices()->where('status', InvoiceStatus::DUE)->count());

        // Access is dated from activation and lasts exactly the ninety days
        // the offering sells — not "until someone remembers to switch it off".
        $this->assertNotNull($entitlement->fresh()->expires_at);
        $this->assertSame(
            90,
            (int) $enrolment->fresh()->activated_at->diffInDays($entitlement->fresh()->expires_at),
        );

        // 5 — the app activates with the token it was given.
        $activation = $this->postJson('/api/v1/tokens/activate', [
            'token' => $plain,
            'device_name' => 'Learner phone',
            'platform' => 'android',
            'app_version' => '1.0.0',
        ]);

        $activation->assertCreated()
            ->assertJsonPath('learner.learner_ref', $learnerRef)
            ->assertJsonPath('activated_programme', 'PPO');

        $deviceToken = $activation->json('device_token');
        $this->assertNotEmpty($deviceToken);

        // The app sees one active programme and the rest of the catalogue as
        // available or locked — never more than it paid for.
        $states = collect($activation->json('entitlements'))->pluck('state', 'programme_code');
        $this->assertSame('active', $states['PPO']);
        $this->assertSame('locked', $states['BIA'], 'a professional specialisation stays locked');

        // 6 — the device token works for the authenticated endpoints.
        $this->withHeader('Authorization', "Bearer {$deviceToken}")
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('learner.learner_ref', $learnerRef);

        $this->assertSame(TokenStatus::ACTIVE, AccessToken::firstOrFail()->status);
    }

    public function test_confirming_the_same_payment_twice_changes_nothing(): void
    {
        [$enrolment] = $this->learnerReadyToPay();
        $activator = app(EnrolmentActivator::class);
        $invoice = $enrolment->activatingInvoice();

        $first = $activator->confirmInvoiceManually($invoice, 'manual', 'DUPLICATE-REF');
        $second = $activator->confirmInvoiceManually($invoice->fresh(), 'manual', 'DUPLICATE-REF');

        $this->assertFalse($first['already_settled']);
        $this->assertTrue($second['already_settled'], 'a replayed confirmation must be a no-op');

        $this->assertSame(1, Payment::count(), 'one payment, not two');
        $this->assertSame(1, AccessToken::count(), 'one token, not two');
        $this->assertNull($second['plain_token']);
    }

    public function test_a_replayed_webhook_delivery_does_not_create_a_second_learner(): void
    {
        $payload = $this->formEightPayload();

        $this->postSignedApplication($payload)->assertCreated();
        $this->postSignedApplication($payload)
            ->assertOk()
            ->assertJson(['status' => 'duplicate_ignored']);

        $this->assertSame(1, Learner::count());
        $this->assertSame(1, Application::count());
    }

    public function test_an_unsigned_application_is_refused(): void
    {
        $this->postJson('/api/v1/intake/application', $this->formEightPayload())
            ->assertUnauthorized();

        $this->assertSame(0, Learner::count());
    }

    public function test_payment_without_identity_activates_the_enrolment_but_withholds_the_token(): void
    {
        // Same journey, but the applicant left the optional ID field blank.
        $payload = $this->formEightPayload();
        unset($payload['applicant']['id_type'], $payload['applicant']['id_number']);

        $this->postSignedApplication($payload)->assertCreated();

        $application = Application::firstOrFail();
        $learner = $application->learner;
        $this->assertFalse($learner->hasVerifiedIdentity());

        $enrolment = app(ApplicationAcceptor::class)->accept($application, $this->careerModuleOffering());

        $result = app(EnrolmentActivator::class)
            ->confirmInvoiceManually($enrolment->activatingInvoice(), 'manual', 'NO-ID-REF');

        // Paid and enrolled — money is not held hostage to a form field.
        $this->assertSame(EnrolmentStatus::ACTIVE, $enrolment->fresh()->status);
        // But no token, and the state says exactly why.
        $this->assertNull($result['plain_token']);
        $this->assertSame(0, AccessToken::count());
        $this->assertSame(ApplicationStatus::PROFILE_INCOMPLETE, $application->fresh()->status);

        // The registrar sights the passport and issues the token.
        $learner->fresh()->update(['identity_verified_at' => now()]);

        $issued = app(AccessTokenIssuer::class)->issue($enrolment->fresh());
        $this->assertNotNull($issued['plain']);
        $this->assertSame(1, AccessToken::count());
    }

    public function test_a_revoked_token_cannot_activate_a_device(): void
    {
        [$enrolment] = $this->learnerReadyToPay();

        $result = app(EnrolmentActivator::class)
            ->confirmInvoiceManually($enrolment->activatingInvoice(), 'manual', 'REVOKE-REF');

        $plain = $result['plain_token'];
        app(AccessTokenIssuer::class)->revoke(AccessToken::firstOrFail(), 'Lost phone');

        $this->postJson('/api/v1/tokens/activate', [
            'token' => $plain,
            'device_name' => 'Found phone',
        ])->assertStatus(422);
    }

    public function test_a_token_typed_without_hyphens_still_works(): void
    {
        [$enrolment] = $this->learnerReadyToPay();

        $result = app(EnrolmentActivator::class)
            ->confirmInvoiceManually($enrolment->activatingInvoice(), 'manual', 'TYPO-REF');

        $mangled = strtolower(str_replace('-', ' ', (string) $result['plain_token']));

        $this->postJson('/api/v1/tokens/activate', [
            'token' => $mangled,
            'device_name' => 'Learner phone',
        ])->assertCreated();
    }

    public function test_an_unpriced_offering_cannot_be_sold(): void
    {
        $this->postSignedApplication($this->formEightPayload())->assertCreated();

        // A Professional Specialisation: fees on enquiry, so no price and no
        // sale until somebody sets one.
        $draft = Offering::where('code', 'BIA-2027-BLOCK')->firstOrFail();
        $this->assertSame(OfferingStatus::DRAFT, $draft->status);

        $application = Application::firstOrFail();
        $application->update(['programme_id' => $draft->programme_id]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/not open/');

        app(ApplicationAcceptor::class)->accept($application->fresh(), $draft);
    }

    public function test_the_public_endpoints_need_no_authentication(): void
    {
        $this->getJson('/api/v1/health')->assertOk()->assertJsonPath('status', 'ok');
        $this->getJson('/api/v1/programmes')->assertOk()->assertJsonCount(15, 'data');
    }

    public function test_the_authenticated_endpoints_refuse_an_anonymous_caller(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
        $this->getJson('/api/v1/me/entitlements')->assertUnauthorized();
    }

    // ---------------------------------------------------------------- helpers

    /** @return array{0: Enrolment} */
    /**
     * People & Payroll Operations as it is actually sold: R500 registration,
     * then R950 a month for three months, with ninety days of access from the
     * day the registration fee lands.
     */
    private function careerModuleOffering(): Offering
    {
        $offering = Offering::where('code', 'PPO-2027-BLOCK')->firstOrFail();

        $this->assertSame(BillingModel::DEPOSIT_BALANCE, $offering->billing_model);
        $this->assertSame(335000, $offering->price_cents);
        $this->assertSame(50000, $offering->deposit_cents);
        $this->assertSame(3, $offering->instalment_count);
        $this->assertSame(OfferingStatus::OPEN, $offering->status);

        return $offering;
    }

    private function learnerReadyToPay(): array
    {
        $this->postSignedApplication($this->formEightPayload())->assertCreated();

        $enrolment = app(ApplicationAcceptor::class)
            ->accept(Application::firstOrFail(), $this->careerModuleOffering());

        return [$enrolment];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postSignedApplication(array $payload): TestResponse
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        return $this->call(
            method: 'POST',
            uri: '/api/v1/intake/application',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_KCS_SIGNATURE' => 'sha256='.hash_hmac('sha256', $body, 'test-secret'),
            ],
            content: $body,
        );
    }

    /**
     * The shape the live Student Application Form will post, with the two new
     * identification fields.
     *
     * @return array<string, mixed>
     */
    private function formEightPayload(): array
    {
        return [
            'source' => 'fluentform',
            'form_id' => 8,
            'submission_id' => 41,
            'submitted_at' => '2027-01-14T09:12:00+02:00',
            'applicant' => [
                'first_name' => 'Thabiso',
                'last_name' => 'Mokoena',
                'email' => 'thabiso.mokoena@example.co.za',
                'phone' => '082 123 4567',
                'whatsapp' => '082 123 4567',
                'id_type' => 'sa_id',
                'id_number' => self::VALID_SA_ID,
                'highest_qualification' => 'Matric',
                'employment_status' => 'Unemployed',
                'digital_experience' => 'Beginner',
                'career_goals' => 'Office administration work.',
                'referral_source' => 'Facebook',
            ],
            'programme_code' => 'PPO',
            'intake_label' => 'February 2027',
            'enrolment_plan' => 'monthly',
        ];
    }
}
