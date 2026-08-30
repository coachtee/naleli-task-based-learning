<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging;

use App\Models\Application;
use App\Models\Enrolment;
use App\Models\Invoice;
use App\Models\Offering;
use App\Services\Enrolment\ApplicationAcceptor;
use App\Services\Messaging\PaymentMessage;
use Database\Seeders\ProgrammeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The message that closes the gap between a payable reference existing and a
 * learner knowing about it.
 *
 * The rules that matter are the two refusals: never send a reference Pay@ has
 * already closed, and never build a WhatsApp link on a number that is not a
 * mobile — the message carries a learner's name and what they owe, so a link
 * built on a typo opens that chat with a stranger.
 */
class PaymentMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_message_carries_everything_needed_to_pay(): void
    {
        $invoice = $this->mintedInvoice();

        $message = app(PaymentMessage::class)->forInvoice($invoice);

        $this->assertStringContainsString('Hi Thabiso,', $message);
        $this->assertStringContainsString('People & Payroll Operations', $message);
        $this->assertStringContainsString('NAL-2026-00001', $message);
        $this->assertStringContainsString('R500.00', $message);
        $this->assertStringContainsString('117009955499000000010', $message);
        $this->assertStringContainsString('https://payat.io/qr/', $message);
        $this->assertStringContainsString('Shoprite', $message);
    }

    public function test_the_link_opens_whats_app_with_the_message_written(): void
    {
        $link = app(PaymentMessage::class)->whatsAppLinkFor($this->mintedInvoice());

        // The learner registered with 082 123 4567.
        $this->assertStringStartsWith('https://wa.me/27821234567?text=', $link);
        $this->assertStringContainsString(rawurlencode('117009955499000000010'), $link);
    }

    public function test_a_reference_pay_at_has_closed_is_never_sent(): void
    {
        $invoice = $this->mintedInvoice();
        $invoice->forceFill(['payat_state' => 'PAYMENT_CANCELLED'])->save();

        $messages = app(PaymentMessage::class);

        $this->assertNull($messages->forInvoice($invoice->fresh()));
        $this->assertNull($messages->whatsAppLinkFor($invoice->fresh()), 'the button must disappear');
    }

    public function test_an_invoice_with_no_reference_yet_has_nothing_to_send(): void
    {
        $this->assertNull(app(PaymentMessage::class)->forInvoice($this->registrationInvoice()));
    }

    public function test_a_number_that_is_not_a_mobile_produces_no_link(): void
    {
        $invoice = $this->mintedInvoice();
        $invoice->learner->update(['whatsapp' => '011 123 4567', 'phone' => 'ask at reception']);

        $messages = app(PaymentMessage::class);

        // The message still exists — a registrar can read it and send it another
        // way — but no chat is opened with a landline.
        $this->assertNotNull($messages->forInvoice($invoice->fresh()));
        $this->assertNull($messages->whatsAppLinkFor($invoice->fresh()));
    }

    public function test_an_instalment_does_not_claim_the_learner_is_registering_again(): void
    {
        $enrolment = $this->enrolment();
        $instalment = $enrolment->invoices()->where('sequence', 2)->sole();
        $instalment->forceFill([
            'payat_account_number' => '9000000020',
            'payat_source_reference' => '117009955499000000020',
            'payat_payment_link' => 'https://payat.io/qr/117009955499000000020',
        ])->save();

        $message = app(PaymentMessage::class)->forInvoice($instalment->fresh());

        $this->assertStringNotContainsString('registration has started', $message);
        $this->assertStringContainsString('next payment', $message);
        $this->assertStringContainsString('R950.00', $message);
    }

    // ---------------------------------------------------------------- helpers

    private function enrolment(): Enrolment
    {
        $this->seed(ProgrammeSeeder::class);
        config(['webhooks.fluentform.secret' => 'test-secret']);

        $body = json_encode([
            'source' => 'fluentform',
            'form_id' => 15,
            'submission_id' => 77,
            'applicant' => [
                'first_name' => 'Thabiso',
                'last_name' => 'Mokoena',
                'email' => 'thabiso@example.co.za',
                'phone' => '082 123 4567',
                'whatsapp' => '082 123 4567',
            ],
            'programme_code' => 'PPO',
        ], JSON_THROW_ON_ERROR);

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

    private function registrationInvoice(): Invoice
    {
        return $this->enrolment()->activatingInvoice();
    }

    private function mintedInvoice(): Invoice
    {
        $invoice = $this->registrationInvoice();

        $invoice->forceFill([
            'payat_account_number' => '9000000010',
            'payat_source_reference' => '117009955499000000010',
            'payat_payment_link' => 'https://payat.io/qr/117009955499000000010',
            'payat_requested_at' => now(),
        ])->save();

        return $invoice->refresh();
    }
}
