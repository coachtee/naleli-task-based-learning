<?php

declare(strict_types=1);

namespace App\Services\Enrolment;

use App\Enums\ApplicationStatus;
use App\Enums\EnrolmentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\LearnerStatus;
use App\Enums\PaymentStatus;
use App\Models\AccessToken;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\Contracts\CallbackResult;
use App\Services\Payments\PaymentProviderRegistry;
use App\Services\Registration\ProfileCompleteness;
use App\Services\Tokens\AccessTokenIssuer;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * The rule the whole system turns on: payment settled → enrolment activated.
 *
 * Every route to "this learner has paid" ends here — a gateway callback in
 * Phase 2, a registrar clicking confirm today — so the guarantees live in one
 * place rather than in each caller. Two of them matter:
 *
 * 1. It is idempotent. Providers retry callbacks and people double-click, so
 *    a payment that is already settled changes nothing on a second pass.
 * 2. It is atomic. The invoice, the enrolment and the entitlement move
 *    together or not at all, because a learner whose payment is recorded but
 *    whose access is not is the worst state this system can be in.
 *
 * Token issuance is deliberately outside the money gate: paying activates the
 * enrolment, verified identity releases the token. A learner who has paid but
 * not yet produced identification sits visibly at awaiting_identity rather
 * than silently stalling.
 */
class EnrolmentActivator
{
    public function __construct(
        private readonly EntitlementResolver $entitlements,
        private readonly AccessTokenIssuer $tokens,
        private readonly ProfileCompleteness $profiles,
    ) {}

    /**
     * Record a settled payment and activate whatever it unlocks.
     *
     * @return array{payment: Payment, token: ?AccessToken, plain_token: ?string, already_settled: bool}
     */
    public function settle(CallbackResult $result, ?Invoice $invoice = null, ?User $actor = null): array
    {
        return DB::transaction(function () use ($result, $invoice, $actor) {
            // The unique index on (provider, provider_reference) is what makes
            // a replayed callback harmless: we find the existing row rather
            // than writing a second payment for the same money.
            $payment = Payment::where('provider', $result->provider)
                ->where('provider_reference', $result->providerReference)
                ->lockForUpdate()
                ->first();

            if ($payment === null) {
                if ($invoice === null) {
                    throw new DomainException(
                        "No invoice supplied for unknown payment {$result->provider}:{$result->providerReference}.",
                    );
                }

                $payment = Payment::create([
                    'invoice_id' => $invoice->id,
                    'learner_id' => $invoice->learner_id,
                    'amount_cents' => $result->amountCents,
                    'currency' => $invoice->currency,
                    'provider' => $result->provider,
                    'provider_reference' => $result->providerReference,
                    'status' => PaymentStatus::INITIATED,
                    'confirmed_by' => $actor?->id,
                ]);
            }

            if ($payment->status === PaymentStatus::SETTLED) {
                return [
                    'payment' => $payment,
                    'token' => null,
                    'plain_token' => null,
                    'already_settled' => true,
                ];
            }

            $payment->update([
                'status' => $result->status,
                'paid_at' => $result->isSettled() ? now() : null,
                'raw_response' => $result->raw,
                'confirmed_by' => $payment->confirmed_by ?? $actor?->id,
            ]);

            if (! $result->isSettled()) {
                return [
                    'payment' => $payment->refresh(),
                    'token' => null,
                    'plain_token' => null,
                    'already_settled' => false,
                ];
            }

            $issued = $this->applySettlement($payment, $actor);

            return [
                'payment' => $payment->refresh(),
                'token' => $issued['token'],
                'plain_token' => $issued['plain'],
                'already_settled' => false,
            ];
        });
    }

    /**
     * Confirm an invoice out of band — the registrar's "payment received"
     * button, and the only route to settlement until a gateway is approved.
     *
     * @return array{payment: Payment, token: ?AccessToken, plain_token: ?string, already_settled: bool}
     */
    public function confirmInvoiceManually(
        Invoice $invoice,
        string $providerKey = 'manual',
        ?string $reference = null,
        ?User $actor = null,
    ): array {
        $provider = app(PaymentProviderRegistry::class)->get($providerKey);

        $payment = Payment::firstOrNew([
            'provider' => $providerKey,
            'provider_reference' => $reference !== null && $reference !== ''
                ? $reference
                : "manual-invoice-{$invoice->id}",
        ]);

        $payment->fill([
            'invoice_id' => $invoice->id,
            'learner_id' => $invoice->learner_id,
            'amount_cents' => $invoice->amount_cents,
            'currency' => $invoice->currency,
            'status' => $payment->status ?? PaymentStatus::INITIATED,
            'confirmed_by' => $actor?->id,
        ])->save();

        return $this->settle($provider->confirmManually($payment->refresh(), $payment->provider_reference), $invoice, $actor);
    }

    /**
     * @return array{token: ?AccessToken, plain: ?string}
     */
    private function applySettlement(Payment $payment, ?User $actor): array
    {
        $invoice = $payment->invoice;

        if ($invoice === null) {
            return ['token' => null, 'plain' => null];
        }

        $invoice->update([
            'status' => InvoiceStatus::PAID,
            'paid_at' => now(),
        ]);

        // Only the invoice carrying the flag turns the enrolment on. Which one
        // that is depends on the commercial model — a single full payment, a
        // deposit, or block one of three — and changing the model changes only
        // which row carries the flag, never this code.
        if (! $invoice->activates_enrolment) {
            return ['token' => null, 'plain' => null];
        }

        $enrolment = $invoice->enrolment;
        $learner = $enrolment->learner;

        if ($enrolment->status !== EnrolmentStatus::ACTIVE) {
            $enrolment->update([
                'status' => EnrolmentStatus::ACTIVE,
                'activated_at' => now(),
            ]);
        }

        $learner->update(['status' => LearnerStatus::ACTIVE]);

        // Paid is its own rung. Whether they are fully registered depends on
        // what is still owed, and that is settled a few lines below.
        $enrolment->application?->update([
            'status' => ApplicationStatus::PAID,
            'registered_at' => null,
        ]);

        $this->entitlements->resolveFor($learner->refresh());

        return $this->issueTokenIfIdentified($enrolment->refresh(), $actor);
    }

    /**
     * @return array{token: ?AccessToken, plain: ?string}
     */
    private function issueTokenIfIdentified($enrolment, ?User $actor): array
    {
        $learner = $enrolment->learner;

        if (! $learner->hasVerifiedIdentity()) {
            // Paid and enrolled, but no token yet. Parked at a named rung so
            // the registrar chases the missing document rather than wondering
            // why nothing arrived — and so the learner can see what is left.
            $this->settleRegistrationStatus($enrolment, $learner);

            return ['token' => null, 'plain' => null];
        }

        $existing = $enrolment->accessTokens()->first();

        $issued = $existing !== null
            ? ['token' => $existing, 'plain' => null]
            : $this->tokens->issue($enrolment, $actor);

        // Identity is the only thing that gates the token. The rest of the
        // profile is chased while the learner is already studying.
        $this->settleRegistrationStatus($enrolment, $learner);

        return ['token' => $issued['token'], 'plain' => $issued['plain'] ?? null];
    }

    /**
     * Registered, or still owing us something. Computed from the record rather
     * than trusted to whoever last touched the form.
     */
    private function settleRegistrationStatus($enrolment, $learner): void
    {
        $complete = $this->profiles->refresh($learner->refresh());

        $enrolment->application?->update($complete
            ? ['status' => ApplicationStatus::REGISTERED, 'registered_at' => now()]
            : ['status' => ApplicationStatus::PROFILE_INCOMPLETE, 'registered_at' => null]);
    }
}
