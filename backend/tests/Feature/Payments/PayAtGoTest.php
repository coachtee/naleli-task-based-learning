<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Enums\EnrolmentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Application;
use App\Models\Enrolment;
use App\Models\Invoice;
use App\Models\Offering;
use App\Models\Payment;
use App\Services\Enrolment\ApplicationAcceptor;
use App\Services\Payments\PayAtGo\PayAtGoException;
use App\Services\Payments\Providers\PayAtGoProvider;
use Database\Seeders\ProgrammeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Pay@ Go, from minting a payable reference to settling on one.
 *
 * The test that matters most here is the forged callback. Pay@ does not sign
 * its notification, so the endpoint has to be safe when a stranger posts
 * `{"status":"PAID"}` at it — and the only way that holds is if the backend
 * ignores the body's claims entirely and asks Pay@ what really happened.
 */
class PayAtGoTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/payments/payat';

    private const TOKEN_URL = 'https://go.payat.co.za/yapi/oauth/token';

    private const CREATE_URL = 'https://go.payat.co.za/yapi/v1/merchant/rtp/create/single';

    private const READ_URL = 'https://go.payat.co.za/yapi/v1/merchant/rtp/read/*';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ProgrammeSeeder::class);

        config([
            'payat.client_id' => 'client-test',
            'payat.client_secret' => 'secret-test',
            'payat.account_prefix' => '9',
            'payat.account_width' => 10,
        ]);
    }

    // ------------------------------------------------------- minting a reference

    public function test_creating_a_checkout_mints_a_payable_reference_and_records_it(): void
    {
        $invoice = $this->registrationInvoice();

        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'tok-1', 'expires_in' => 3599]),
            self::CREATE_URL => Http::response($this->rtp($invoice, [
                'requestToPayId' => 'rtp-778',
                'sourceReference' => '11700995549'.$this->expectedAccountNumber($invoice),
                'paymentLink' => 'https://payat.io/qr/11700995549'.$this->expectedAccountNumber($invoice),
            ])),
        ]);

        $intent = app(PayAtGoProvider::class)->createCheckout($invoice);

        $this->assertNotNull($intent);
        $this->assertSame($this->expectedAccountNumber($invoice), $intent->providerReference);
        $this->assertStringStartsWith('https://payat.io/qr/', (string) $intent->redirectUrl);

        $invoice->refresh();
        $this->assertSame($this->expectedAccountNumber($invoice), $invoice->payat_account_number);
        $this->assertSame('rtp-778', $invoice->payat_request_to_pay_id);
        $this->assertNotNull($invoice->payat_payment_link);
        $this->assertNotNull($invoice->payat_requested_at);

        // The reference is ours, ten digits, prefixed so it can never be
        // confused with the learner mobile numbers used before this existed.
        $this->assertMatchesRegularExpression('/^9\d{9}$/', $invoice->payat_account_number);
    }

    public function test_the_token_is_fetched_with_basic_auth_and_explicit_scopes(): void
    {
        $invoice = $this->registrationInvoice();

        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'tok-1', 'expires_in' => 3599]),
            self::CREATE_URL => Http::response($this->rtp($invoice)),
        ]);

        app(PayAtGoProvider::class)->createCheckout($invoice);

        // Both of these are load-bearing: credentials in the POST body return
        // `invalid_client`, and a token minted without scopes 403s on use.
        Http::assertSent(function (ClientRequest $request): bool {
            if ($request->url() !== self::TOKEN_URL) {
                return false;
            }

            return $request->hasHeader('Authorization', 'Basic '.base64_encode('client-test:secret-test'))
                && $request['grant_type'] === 'client_credentials'
                && $request['scope'] === 'rtp:create:single rtp:cancel:single rtp:read';
        });
    }

    public function test_the_amount_sent_to_pay_at_is_the_invoice_in_cents(): void
    {
        $invoice = $this->registrationInvoice();

        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'tok-1', 'expires_in' => 3599]),
            self::CREATE_URL => Http::response($this->rtp($invoice)),
        ]);

        app(PayAtGoProvider::class)->createCheckout($invoice);

        Http::assertSent(function (ClientRequest $request) use ($invoice): bool {
            if ($request->url() !== self::CREATE_URL) {
                return false;
            }

            return $request['amount'] === 50000
                && $request['amount'] === $invoice->amount_cents
                && $request['maximumAmount'] === 50000
                && $request['customerNameSurname'] === 'Thabiso Mokoena'
                && $request['lineItems'][0]['amount'] === 50000;
        });
    }

    /**
     * The registration fee is due the day it is raised. Deriving the payable
     * window from the due date alone minted a reference that expired at
     * midnight the same day — dead before anyone had sent it to the learner.
     */
    public function test_a_fee_due_today_is_still_payable_for_the_configured_window(): void
    {
        config(['payat.days_valid' => 60]);
        $invoice = $this->registrationInvoice();

        $this->assertTrue($invoice->due_on->isToday(), 'the registration fee is due immediately');

        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'tok-1', 'expires_in' => 3599]),
            self::CREATE_URL => Http::response($this->rtp($invoice)),
        ]);

        app(PayAtGoProvider::class)->createCheckout($invoice);

        Http::assertSent(fn (ClientRequest $r): bool => $r->url() === self::CREATE_URL
            && $r['daysValid'] === 60);
    }

    public function test_an_instalment_due_beyond_the_window_stays_payable_until_it_is_due(): void
    {
        config(['payat.days_valid' => 60]);

        $invoice = $this->enrolment()->invoices()->where('sequence', 4)->sole();
        $daysOut = (int) ceil(now()->startOfDay()->diffInDays($invoice->due_on->endOfDay(), false));
        $this->assertGreaterThan(60, $daysOut, 'the last instalment falls outside the default window');

        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'tok-1', 'expires_in' => 3599]),
            self::CREATE_URL => Http::response($this->rtp($invoice)),
        ]);

        app(PayAtGoProvider::class)->createCheckout($invoice);

        Http::assertSent(fn (ClientRequest $r): bool => $r->url() === self::CREATE_URL
            && $r['daysValid'] === $daysOut && $r['daysValid'] <= 120);
    }

    public function test_a_second_checkout_reuses_the_reference_the_learner_was_already_given(): void
    {
        $invoice = $this->registrationInvoice();

        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'tok-1', 'expires_in' => 3599]),
            self::CREATE_URL => Http::response($this->rtp($invoice)),
        ]);

        $first = app(PayAtGoProvider::class)->createCheckout($invoice);
        $second = app(PayAtGoProvider::class)->createCheckout($invoice->fresh());

        $this->assertSame($first->providerReference, $second->providerReference);
        Http::assertSentCount(2); // the token and one create — never a second reference
    }

    public function test_a_create_that_fails_because_the_reference_already_exists_adopts_it(): void
    {
        $invoice = $this->registrationInvoice();

        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'tok-1', 'expires_in' => 3599]),
            self::CREATE_URL => Http::response(['message' => 'clientAccountNumber already in use'], 409),
            // Still outstanding, so a learner can still pay it.
            self::READ_URL => Http::response($this->rtp($invoice, ['requestToPayId' => 'rtp-existing'])),
        ]);

        $intent = app(PayAtGoProvider::class)->createCheckout($invoice);

        $this->assertSame($this->expectedAccountNumber($invoice), $intent->providerReference);
        $this->assertSame('rtp-existing', $invoice->fresh()->payat_request_to_pay_id);
    }

    /**
     * The defect this guards was found by walking the flow against the live
     * account: a cancelled reference read back after a failed create was
     * adopted and stored, handing the learner a number that can never be paid
     * and telling the registrar it was payable.
     */
    public function test_a_dead_reference_is_never_adopted_as_if_it_were_payable(): void
    {
        $invoice = $this->registrationInvoice();

        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'tok-1', 'expires_in' => 3599]),
            self::CREATE_URL => Http::response(['message' => 'clientAccountNumber already in use'], 409),
            self::READ_URL => Http::response($this->rtp($invoice, ['accountState' => 'PAYMENT_CANCELLED'])),
        ]);

        try {
            app(PayAtGoProvider::class)->createCheckout($invoice);
            $this->fail('A cancelled reference must not be adopted.');
        } catch (PayAtGoException $e) {
            $this->assertStringContainsString('cannot be paid', $e->getMessage());
        }

        $this->assertNull($invoice->fresh()->payat_account_number, 'nothing dead was stored');
    }

    public function test_re_issuing_allocates_a_fresh_number_the_learner_can_pay(): void
    {
        $invoice = $this->mintedInvoice();
        $dead = $invoice->payat_account_number;

        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'tok-1', 'expires_in' => 3599]),
            self::CREATE_URL => fn (ClientRequest $request) => Http::response($this->rtp($invoice, [
                'clientAccountNumber' => $request['clientAccountNumber'],
                'accountState' => 'PAYMENT_OUTSTANDING',
            ])),
        ]);

        $intent = app(PayAtGoProvider::class)->reissue($invoice);

        $this->assertNotSame($dead, $intent->providerReference, 'Pay@ will not reuse a number');
        $this->assertSame(1, (int) $invoice->fresh()->payat_attempt);
        $this->assertSame($intent->providerReference, $invoice->fresh()->payat_account_number);
        $this->assertMatchesRegularExpression('/^9\d{9}$/', $intent->providerReference);
    }

    public function test_reconciling_records_the_state_pay_at_reported(): void
    {
        $invoice = $this->mintedInvoice();

        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'tok-1', 'expires_in' => 3599]),
            self::READ_URL => Http::response($this->rtp($invoice, ['accountState' => 'PAYMENT_EXPIRED'])),
        ]);

        app(PayAtGoProvider::class)->reconcile($invoice);

        // So the dashboard can say "expired, issue a new one" without calling
        // Pay@ on every page load.
        $this->assertSame('PAYMENT_EXPIRED', $invoice->fresh()->payat_state);
    }

    public function test_without_credentials_checkout_falls_back_to_the_counter_rather_than_failing(): void
    {
        config(['payat.client_id' => '', 'payat.client_secret' => '']);
        Http::fake();

        $this->assertNull(app(PayAtGoProvider::class)->createCheckout($this->registrationInvoice()));
        Http::assertNothingSent();
    }

    public function test_an_unusable_mobile_number_does_not_stop_a_reference_being_created(): void
    {
        $invoice = $this->registrationInvoice();
        $invoice->learner->update(['phone' => 'call the office', 'whatsapp' => null]);

        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'tok-1', 'expires_in' => 3599]),
            self::CREATE_URL => Http::response($this->rtp($invoice)),
        ]);

        $this->assertNotNull(app(PayAtGoProvider::class)->createCheckout($invoice->fresh()));

        Http::assertSent(fn (ClientRequest $r): bool => $r->url() === self::CREATE_URL
            && ! array_key_exists('customerMobileNumber', $r->data()));
    }

    // ------------------------------------------------------------ the callback

    public function test_a_forged_callback_claiming_payment_settles_nothing(): void
    {
        $invoice = $this->mintedInvoice();

        // Pay@ is the authority, and Pay@ says nobody has paid.
        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'tok-1', 'expires_in' => 3599]),
            self::READ_URL => Http::response($this->rtp($invoice, [
                'accountState' => 'PAYMENT_OUTSTANDING',
                'amountPaid' => 0,
            ])),
        ]);

        $this->postJson(self::ENDPOINT, [
            'clientAccountNumber' => $invoice->payat_account_number,
            'sourceReference' => $invoice->payat_source_reference,
            'amount' => 50000,
            'status' => 'PAID',
            'customerNameSurname' => 'Thabiso Mokoena',
        ])->assertOk()->assertJsonPath('status', 'no_payment');

        $this->assertSame(0, Payment::count());
        $this->assertSame(InvoiceStatus::DUE, $invoice->fresh()->status);
        $this->assertSame(EnrolmentStatus::PENDING, $invoice->enrolment->fresh()->status);
    }

    public function test_a_genuine_payment_activates_the_enrolment(): void
    {
        $invoice = $this->mintedInvoice();

        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'tok-1', 'expires_in' => 3599]),
            self::READ_URL => Http::response($this->rtp($invoice, [
                'accountState' => 'PAYMENT_COMPLETED',
                'amountPaid' => 50000,
                'dateTimePaid' => '2027-01-20T14:03:11+02:00',
                'tenderType' => 'CASH',
                'paymentNetwork' => 'Shoprite',
            ])),
        ]);

        $this->postJson(self::ENDPOINT, ['sourceReference' => $invoice->payat_source_reference, 'status' => 'PAID'])
            ->assertOk()
            ->assertJsonPath('status', 'settled');

        $payment = Payment::sole();
        $this->assertSame('payat_go', $payment->provider);
        $this->assertSame($invoice->payat_account_number, $payment->provider_reference);
        $this->assertSame(PaymentStatus::SETTLED, $payment->status);
        $this->assertSame(50000, $payment->amount_cents);
        $this->assertSame('CASH', $payment->raw_response['tender_type']);

        $this->assertSame(InvoiceStatus::PAID, $invoice->fresh()->status);
        $this->assertSame(EnrolmentStatus::ACTIVE, $invoice->enrolment->fresh()->status);
    }

    public function test_a_part_payment_is_recorded_but_activates_nothing(): void
    {
        $invoice = $this->mintedInvoice();

        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'tok-1', 'expires_in' => 3599]),
            self::READ_URL => Http::response($this->rtp($invoice, [
                'accountState' => 'PARTIAL_PAYMENT_RECEIVED',
                'amountPaid' => 20000,
            ])),
        ]);

        $this->postJson(self::ENDPOINT, ['clientAccountNumber' => $invoice->payat_account_number])
            ->assertOk()
            ->assertJsonPath('status', 'pending');

        $payment = Payment::sole();
        $this->assertSame(PaymentStatus::PENDING, $payment->status);
        $this->assertSame(20000, $payment->amount_cents, 'what actually arrived, not what is owed');
        $this->assertNull($payment->paid_at);

        $this->assertSame(InvoiceStatus::DUE, $invoice->fresh()->status);
        $this->assertSame(EnrolmentStatus::PENDING, $invoice->enrolment->fresh()->status);
    }

    public function test_a_part_payment_that_is_later_completed_settles_the_same_row(): void
    {
        $invoice = $this->mintedInvoice();

        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'tok-1', 'expires_in' => 3599]),
            self::READ_URL => Http::sequence()
                ->push($this->rtp($invoice, ['accountState' => 'PARTIAL_PAYMENT_RECEIVED', 'amountPaid' => 20000]))
                ->push($this->rtp($invoice, ['accountState' => 'PAYMENT_COMPLETED', 'amountPaid' => 50000])),
        ]);

        $body = ['clientAccountNumber' => $invoice->payat_account_number];

        $this->postJson(self::ENDPOINT, $body)->assertOk();
        $this->postJson(self::ENDPOINT, $body)->assertOk()->assertJsonPath('status', 'settled');

        $this->assertSame(1, Payment::count(), 'one reference, one payment row');

        $payment = Payment::sole();
        $this->assertSame(PaymentStatus::SETTLED, $payment->status);
        $this->assertSame(50000, $payment->amount_cents);
        $this->assertSame(EnrolmentStatus::ACTIVE, $invoice->enrolment->fresh()->status);
    }

    public function test_a_replayed_callback_settles_once(): void
    {
        $invoice = $this->mintedInvoice();

        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'tok-1', 'expires_in' => 3599]),
            self::READ_URL => Http::response($this->rtp($invoice, [
                'accountState' => 'SETTLEMENT_PROCESSED',
                'amountPaid' => 50000,
            ])),
        ]);

        $body = ['clientAccountNumber' => $invoice->payat_account_number];

        $this->postJson(self::ENDPOINT, $body)->assertOk()->assertJsonPath('status', 'settled');
        $this->postJson(self::ENDPOINT, $body)->assertOk()->assertJsonPath('status', 'already_settled');

        $this->assertSame(1, Payment::count());
    }

    public function test_a_callback_naming_a_reference_pay_at_does_not_know_is_ignored(): void
    {
        $this->mintedInvoice();

        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'tok-1', 'expires_in' => 3599]),
            self::READ_URL => Http::response(['message' => 'not found'], 404),
        ]);

        $this->postJson(self::ENDPOINT, ['clientAccountNumber' => '9000000999'])
            ->assertOk()
            ->assertJsonPath('status', 'ignored');

        $this->assertSame(0, Payment::count());
    }

    public function test_a_callback_that_identifies_nothing_of_ours_never_reaches_pay_at(): void
    {
        Http::fake();

        $this->postJson(self::ENDPOINT, ['status' => 'PAID', 'amount' => 999999])
            ->assertOk()
            ->assertJsonPath('status', 'ignored');

        Http::assertNothingSent();
        $this->assertSame(0, Payment::count());
    }

    public function test_every_delivery_is_recorded_whether_or_not_it_is_genuine(): void
    {
        Http::fake();

        $this->postJson(self::ENDPOINT, ['status' => 'PAID', 'requestToPayId' => 'rtp-nonsense'])->assertOk();

        $this->assertDatabaseHas('inbound_webhooks', [
            'source' => 'payat_go',
            'event_type' => 'rtp.notification',
            'external_id' => 'rtp-nonsense',
            'signature_valid' => false,
        ]);
    }

    public function test_a_cancelled_reference_is_recorded_as_cancelled_not_settled(): void
    {
        $invoice = $this->mintedInvoice();

        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'tok-1', 'expires_in' => 3599]),
            self::READ_URL => Http::response($this->rtp($invoice, [
                'accountState' => 'PAYMENT_EXPIRED',
                'amountPaid' => 0,
            ])),
        ]);

        $this->postJson(self::ENDPOINT, ['clientAccountNumber' => $invoice->payat_account_number])->assertOk();

        $this->assertSame(0, Payment::count(), 'nothing was paid, so nothing is recorded');
        $this->assertSame(InvoiceStatus::DUE, $invoice->fresh()->status);
    }

    /**
     * PAYMENT_FEES_ISSUE means money exists and Pay@ has flagged something
     * about it. Held short of settlement on purpose, so a registrar looks.
     */
    public function test_a_flagged_payment_is_held_for_a_registrar(): void
    {
        $invoice = $this->mintedInvoice();

        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'tok-1', 'expires_in' => 3599]),
            self::READ_URL => Http::response($this->rtp($invoice, [
                'accountState' => 'PAYMENT_FEES_ISSUE',
                'amountPaid' => 50000,
            ])),
        ]);

        $this->postJson(self::ENDPOINT, ['clientAccountNumber' => $invoice->payat_account_number])->assertOk();

        $this->assertSame(PaymentStatus::PENDING, Payment::sole()->status);
        $this->assertSame(EnrolmentStatus::PENDING, $invoice->enrolment->fresh()->status);
    }

    // ------------------------------------------------------------ reconciliation

    public function test_an_invoice_can_be_reconciled_without_any_callback_at_all(): void
    {
        $invoice = $this->mintedInvoice();

        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'tok-1', 'expires_in' => 3599]),
            self::READ_URL => Http::response($this->rtp($invoice, [
                'accountState' => 'PAYMENT_COMPLETED',
                'amountPaid' => 50000,
            ])),
        ]);

        $result = app(PayAtGoProvider::class)->reconcile($invoice);

        $this->assertNotNull($result);
        $this->assertTrue($result->isSettled());
        $this->assertSame(50000, $result->amountCents);
    }

    public function test_reconciling_an_invoice_with_no_reference_asks_pay_at_nothing(): void
    {
        Http::fake();

        $this->assertNull(app(PayAtGoProvider::class)->reconcile($this->registrationInvoice()));
        Http::assertNothingSent();
    }

    // ----------------------------------------------------------------- the token

    public function test_a_stale_token_is_refreshed_once_rather_than_failing_the_call(): void
    {
        $invoice = $this->mintedInvoice();

        Http::fake([
            self::TOKEN_URL => Http::sequence()
                ->push(['access_token' => 'stale', 'expires_in' => 3599])
                ->push(['access_token' => 'fresh', 'expires_in' => 3599]),
            self::READ_URL => Http::sequence()
                ->push(['message' => 'forbidden'], 403)
                ->push($this->rtp($invoice, ['accountState' => 'PAYMENT_COMPLETED', 'amountPaid' => 50000])),
        ]);

        $result = app(PayAtGoProvider::class)->reconcile($invoice);

        $this->assertTrue($result?->isSettled());
        Http::assertSent(fn (ClientRequest $r): bool => $r->hasHeader('Authorization', 'Bearer fresh'));
    }

    public function test_the_reconcile_sweep_settles_a_payment_no_callback_ever_announced(): void
    {
        $invoice = $this->mintedInvoice();

        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'tok-1', 'expires_in' => 3599]),
            self::READ_URL => Http::response($this->rtp($invoice, [
                'accountState' => 'PAYMENT_COMPLETED',
                'amountPaid' => 50000,
            ])),
        ]);

        $this->artisan('payat:reconcile')
            ->expectsOutputToContain('1 settled')
            ->assertSuccessful();

        $this->assertSame(InvoiceStatus::PAID, $invoice->fresh()->status);
        $this->assertSame(EnrolmentStatus::ACTIVE, $invoice->enrolment->fresh()->status);
        $this->assertSame(PaymentStatus::SETTLED, Payment::sole()->status);
    }

    public function test_the_reconcile_sweep_leaves_an_unpaid_reference_alone(): void
    {
        $invoice = $this->mintedInvoice();

        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'tok-1', 'expires_in' => 3599]),
            self::READ_URL => Http::response($this->rtp($invoice)),
        ]);

        $this->artisan('payat:reconcile')->assertSuccessful();

        $this->assertSame(0, Payment::count());
        $this->assertSame(InvoiceStatus::DUE, $invoice->fresh()->status);
    }

    public function test_the_reconcile_sweep_asks_nothing_without_credentials(): void
    {
        config(['payat.client_id' => '', 'payat.client_secret' => '']);
        Http::fake();

        $this->artisan('payat:reconcile')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_account_numbers_are_unique_per_invoice(): void
    {
        $enrolment = $this->enrolment();
        $provider = app(PayAtGoProvider::class);

        $numbers = $enrolment->invoices->map(fn (Invoice $i): string => $provider->accountNumberFor($i));

        $this->assertCount(4, $numbers);
        $this->assertSame($numbers->unique()->count(), $numbers->count());
        $numbers->each(fn (string $n) => $this->assertMatchesRegularExpression('/^9\d{9}$/', $n));
    }

    // ---------------------------------------------------------------- helpers

    private function enrolment(): Enrolment
    {
        $body = json_encode([
            'source' => 'fluentform',
            'form_id' => 15,
            'submission_id' => 41,
            'applicant' => [
                'first_name' => 'Thabiso',
                'last_name' => 'Mokoena',
                'email' => 'thabiso.mokoena@example.co.za',
                'phone' => '082 123 4567',
            ],
            'programme_code' => 'PPO',
        ], JSON_THROW_ON_ERROR);

        config(['webhooks.fluentform.secret' => 'test-secret']);

        $this->call(
            method: 'POST',
            uri: '/api/v1/intake/application',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_KCS_SIGNATURE' => 'sha256='.hash_hmac('sha256', $body, 'test-secret'),
            ],
            content: $body,
        )->assertCreated();

        return app(ApplicationAcceptor::class)->accept(
            Application::firstOrFail(),
            Offering::where('code', 'PPO-2027')->firstOrFail(),
        );
    }

    /** The R500 registration fee — the invoice that opens access. */
    private function registrationInvoice(): Invoice
    {
        return $this->enrolment()->activatingInvoice();
    }

    /** A registration fee that already carries its Pay@ reference. */
    private function mintedInvoice(): Invoice
    {
        $invoice = $this->registrationInvoice();
        $account = $this->expectedAccountNumber($invoice);

        $invoice->forceFill([
            'payat_account_number' => $account,
            'payat_request_to_pay_id' => 'rtp-778',
            'payat_source_reference' => '11700995549'.$account,
            'payat_payment_link' => 'https://payat.io/qr/11700995549'.$account,
            'payat_requested_at' => now(),
        ])->save();

        return $invoice->refresh();
    }

    private function expectedAccountNumber(Invoice $invoice): string
    {
        return '9'.str_pad((string) $invoice->id, 8, '0', STR_PAD_LEFT).(int) $invoice->payat_attempt;
    }

    /**
     * A Pay@ request-to-pay as the live API actually returns one.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function rtp(Invoice $invoice, array $overrides = []): array
    {
        $account = $invoice->payat_account_number ?? $this->expectedAccountNumber($invoice);

        return array_merge([
            'clientAccountNumber' => $account,
            'clientReferenceNumber' => 'NAL-2026-00001-1',
            'requestToPayId' => 'rtp-778',
            'sourceReference' => '11700995549'.$account,
            'paymentLink' => 'https://payat.io/qr/11700995549'.$account,
            'description' => $invoice->description,
            'customerNameSurname' => 'Thabiso Mokoena',
            'amount' => $invoice->amount_cents,
            'amountPaid' => 0,
            'minimumAmount' => 1000,
            'maximumAmount' => $invoice->amount_cents,
            'businessName' => 'Katlehong Computer School',
            'accountState' => 'PAYMENT_OUTSTANDING',
            'dateTimePaid' => null,
            'paymentNetwork' => null,
            'tenderType' => null,
        ], $overrides);
    }
}
