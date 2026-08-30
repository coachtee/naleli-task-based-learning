<?php

declare(strict_types=1);

namespace App\Services\Payments\Providers;

use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Payments\Contracts\CallbackResult;
use App\Services\Payments\Contracts\CheckoutIntent;
use App\Services\Payments\Contracts\PaymentProvider;
use App\Services\Payments\PayAtGo\PayAtGoClient;
use App\Services\Payments\PayAtGo\PayAtGoException;
use App\Services\Payments\PayAtGo\RequestToPay;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Pay@ Go — cash paid at a retail counter against a reference number.
 *
 * The most accessible method KCS offers: a learner with no bank card, no data
 * and no smartphone can still pay, at any Shoprite, Checkers, Pick n Pay or
 * Boxer till. We create one "request to pay" per invoice; the learner is
 * given the reference (or its QR link over WhatsApp) and pays it in cash.
 *
 * The security property that matters here is that WE NEVER TRUST THE
 * CALLBACK. Pay@'s webhook carries no signature, so a body claiming
 * `status: PAID` proves nothing — anyone who found the URL could post one and
 * activate an enrolment for free. The callback is treated purely as a nudge:
 * every settlement is decided by reading the reference back from Pay@ over an
 * authenticated connection. That means a lost webhook costs a delay, never a
 * missed payment, and a forged one costs nothing at all.
 */
class PayAtGoProvider implements PaymentProvider
{
    public function __construct(private readonly PayAtGoClient $client) {}

    public function key(): string
    {
        return 'payat_go';
    }

    public function label(): string
    {
        return 'Pay@ Go';
    }

    /**
     * The learner leaves us to pay: at a till, or through the QR link. Not a
     * browser redirect in the checkout sense, but the same thing as far as
     * anything above this interface is concerned — we hand back a URL and
     * wait to be told.
     */
    public function isRedirect(): bool
    {
        return true;
    }

    /**
     * Mint the payable reference for an invoice, or hand back the one it
     * already has.
     *
     * Idempotent by construction: the account number is derived from the
     * invoice id, so a retry addresses the same reference rather than minting
     * a second one a learner could pay by mistake.
     */
    public function createCheckout(Invoice $invoice): ?CheckoutIntent
    {
        if ($invoice->payat_account_number !== null) {
            return $this->intentFor($invoice);
        }

        if (! $this->client->isConfigured()) {
            // No merchant credentials: fall back to the counter, where a
            // registrar records the payment by hand. Never a hard failure —
            // this must not be able to block a registration.
            return null;
        }

        $accountNumber = $this->accountNumberFor($invoice);

        try {
            $rtp = $this->client->createRequestToPay($this->creationPayload($invoice, $accountNumber));
        } catch (PayAtGoException $e) {
            // The one recoverable failure: the reference already exists at
            // Pay@ because a previous attempt got through and we lost the
            // answer. Adopting it is right; minting a second is not.
            $rtp = $this->client->readRequestToPay($accountNumber);

            if ($rtp === null) {
                throw $e;
            }
        }

        $invoice->forceFill([
            'payat_account_number' => $rtp->accountNumber !== '' ? $rtp->accountNumber : $accountNumber,
            'payat_request_to_pay_id' => $rtp->requestToPayId,
            'payat_source_reference' => $rtp->sourceReference,
            'payat_payment_link' => $rtp->paymentLink,
            'payat_requested_at' => now(),
        ])->save();

        return $this->intentFor($invoice->refresh());
    }

    /**
     * Interpret a Pay@ callback by ignoring everything it says and asking
     * Pay@ directly.
     *
     * The body is used for one thing only: working out WHICH reference to go
     * and look at. Null means we could not identify a reference of ours, or
     * Pay@ does not agree it exists — either way nothing may be settled.
     */
    public function verifyCallback(Request $request): ?CallbackResult
    {
        $accountNumber = $this->accountNumberFromCallback($request);

        if ($accountNumber === null) {
            return null;
        }

        $rtp = $this->client->readRequestToPay($accountNumber);

        return $rtp === null ? null : $this->resultFor($rtp);
    }

    /**
     * Ask Pay@ where an invoice stands, unprompted.
     *
     * The same verified path as a callback, reachable from the dashboard and
     * from a reconciliation sweep — so a webhook that never arrives is a
     * button press away from being caught rather than a lost payment.
     */
    public function reconcile(Invoice $invoice): ?CallbackResult
    {
        if ($invoice->payat_account_number === null) {
            return null;
        }

        $rtp = $this->client->readRequestToPay($invoice->payat_account_number);

        return $rtp === null ? null : $this->resultFor($rtp);
    }

    public function confirmManually(Payment $payment, ?string $reference): CallbackResult
    {
        return new CallbackResult(
            provider: $this->key(),
            providerReference: $reference ?? 'payat_go-'.$payment->id,
            status: PaymentStatus::SETTLED,
            amountCents: $payment->amount_cents,
            raw: ['confirmed_by_user_id' => $payment->confirmed_by, 'source' => 'pay_at_statement'],
        );
    }

    /**
     * The account number is ours to allocate, and this is the whole scheme:
     * a fixed prefix, then the invoice id.
     *
     * Derived rather than sequential so it is reproducible from the invoice
     * alone, and prefixed so it can never be mistaken for — or collide with —
     * the learner mobile numbers used as references before this backend
     * existed.
     */
    public function accountNumberFor(Invoice $invoice): string
    {
        $prefix = preg_replace('/\D/', '', (string) config('payat.account_prefix', '9')) ?? '';
        $width = min(14, max(strlen($prefix) + 1, (int) config('payat.account_width', 10)));
        $digits = $width - strlen($prefix);
        $id = (string) $invoice->id;

        if (strlen($id) > $digits) {
            throw new InvalidArgumentException(
                "Invoice {$invoice->id} does not fit a {$width}-digit Pay@ account number. ".
                'Raise payat.account_width before the next invoice is raised.',
            );
        }

        return $prefix.str_pad($id, $digits, '0', STR_PAD_LEFT);
    }

    /**
     * @return array<string, mixed>
     */
    private function creationPayload(Invoice $invoice, string $accountNumber): array
    {
        $learner = $invoice->learner;
        $name = trim(($learner?->first_name ?? '').' '.($learner?->last_name ?? ''));

        $payload = [
            'clientAccountNumber' => $accountNumber,
            'clientReferenceNumber' => $this->clientReferenceFor($invoice),
            'customerNameSurname' => $name !== '' ? $name : 'KCS learner',
            'amount' => $invoice->amount_cents,
            'minimumAmount' => min((int) config('payat.minimum_amount_cents', 1000), $invoice->amount_cents),
            'maximumAmount' => $invoice->amount_cents,
            'description' => $invoice->description,
            'merchantDisplayName' => (string) config('payat.merchant_display_name'),
            'daysValid' => $this->daysValidFor($invoice),
            'lineItems' => [
                ['description' => $invoice->description, 'amount' => $invoice->amount_cents],
            ],
        ];

        $mobile = $this->mobileFor($learner?->phone) ?? $this->mobileFor($learner?->whatsapp);

        if ($mobile !== null) {
            $payload['customerMobileNumber'] = $mobile;
            // Pay@ sends the learner their own SMS receipt. Ours is separate.
            $payload['notificationNumber'] = $mobile;
        }

        if (($learner?->email ?? null) !== null && $learner->email !== '') {
            $payload['customerEmail'] = $learner->email;
        }

        return $payload;
    }

    /**
     * Pay@ validates mobile numbers as `^(+27|27|0)?[6-8][0-9]{8}$` and
     * rejects the whole request over one that does not match.
     *
     * A learner who typed their number with a space in it must not end up
     * unable to pay, so anything unusable is dropped and the reference is
     * created without it. The SMS is a convenience; the reference is not.
     */
    private function mobileFor(?string $number): ?string
    {
        if ($number === null || trim($number) === '') {
            return null;
        }

        $clean = preg_replace('/[^\d+]/', '', $number) ?? '';

        return preg_match('/^(\+27|27|0)?[6-8][0-9]{8}$/', $clean) === 1 ? $clean : null;
    }

    /**
     * What finance sees on the Pay@ statement. Legible on purpose: a learner
     * reference and which invoice of theirs it was.
     */
    private function clientReferenceFor(Invoice $invoice): string
    {
        $ref = $invoice->learner?->learner_ref ?? "INV{$invoice->id}";

        return substr("{$ref}-{$invoice->sequence}", 0, 40);
    }

    /** Payable until the invoice is due, inside Pay@'s 1–120 day window. */
    private function daysValidFor(Invoice $invoice): int
    {
        $default = (int) config('payat.days_valid', 60);

        $days = $invoice->due_on !== null
            ? (int) ceil(now()->startOfDay()->diffInDays($invoice->due_on->endOfDay(), false))
            : $default;

        return max(1, min(120, $days > 0 ? $days : $default));
    }

    private function intentFor(Invoice $invoice): CheckoutIntent
    {
        return new CheckoutIntent(
            providerReference: (string) $invoice->payat_account_number,
            redirectUrl: $invoice->payat_payment_link,
            payload: [
                'account_number' => $invoice->payat_account_number,
                'source_reference' => $invoice->payat_source_reference,
                'request_to_pay_id' => $invoice->payat_request_to_pay_id,
                'amount_cents' => $invoice->amount_cents,
            ],
        );
    }

    /**
     * Which of our references a callback is about.
     *
     * Pay@'s callback body has been observed to carry the source reference
     * and the internal id but not always the account number, so all three are
     * accepted — and the two that are not ours to choose are resolved through
     * our own invoices rather than used directly.
     */
    private function accountNumberFromCallback(Request $request): ?string
    {
        $direct = $request->input('clientAccountNumber');

        if (is_scalar($direct) && preg_match('/^\d{1,14}$/', (string) $direct) === 1) {
            return (string) $direct;
        }

        foreach (['sourceReference' => 'payat_source_reference', 'requestToPayId' => 'payat_request_to_pay_id'] as $field => $column) {
            $value = $request->input($field);

            if (! is_scalar($value) || (string) $value === '') {
                continue;
            }

            $invoice = Invoice::where($column, (string) $value)->first();

            if ($invoice?->payat_account_number !== null) {
                return $invoice->payat_account_number;
            }
        }

        return null;
    }

    /**
     * Settlement needs both halves to agree: Pay@ must call the reference
     * paid AND the money must actually cover the invoice. A part payment is
     * recorded as pending, which is true and visible, rather than rounded up
     * into an activation.
     */
    private function resultFor(RequestToPay $rtp): CallbackResult
    {
        $status = match (true) {
            $rtp->isSettled() => PaymentStatus::SETTLED,
            $rtp->state?->isClosed() === true => PaymentStatus::CANCELLED,
            default => PaymentStatus::PENDING,
        };

        return new CallbackResult(
            provider: $this->key(),
            providerReference: $rtp->accountNumber,
            status: $status,
            amountCents: $rtp->amountPaidCents,
            raw: [
                'account_state' => $rtp->state?->value,
                'amount_cents' => $rtp->amountCents,
                'amount_paid_cents' => $rtp->amountPaidCents,
                'source_reference' => $rtp->sourceReference,
                'request_to_pay_id' => $rtp->requestToPayId,
                'payment_network' => $rtp->raw['paymentNetwork'] ?? null,
                'tender_type' => $rtp->raw['tenderType'] ?? null,
                'date_time_paid' => $rtp->raw['dateTimePaid'] ?? null,
            ],
        );
    }
}
