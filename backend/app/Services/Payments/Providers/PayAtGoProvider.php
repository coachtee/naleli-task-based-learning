<?php

declare(strict_types=1);

namespace App\Services\Payments\Providers;

use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Payments\Contracts\CallbackResult;
use App\Services\Payments\Contracts\CheckoutIntent;
use App\Services\Payments\Contracts\PaymentProvider;
use Illuminate\Http\Request;

/**
 * Pay@ Go — cash paid at a retail counter against a reference number.
 *
 * This already works operationally at KCS and is the most accessible method
 * on offer: a learner without a bank card or data can still pay. Recorded as
 * its own provider rather than lumped in with manual entries so takings
 * reconcile against the Pay@ statement line by line.
 */
class PayAtGoProvider implements PaymentProvider
{
    public function key(): string
    {
        return 'payat_go';
    }

    public function label(): string
    {
        return 'Pay@ Go';
    }

    public function isRedirect(): bool
    {
        return false;
    }

    public function createCheckout(Invoice $invoice): ?CheckoutIntent
    {
        return null;
    }

    /**
     * No callback: settlement is a person reading the Pay@ statement and confirming it
     * in the dashboard. When an automated feed exists this is where it lands,
     * and nothing above this interface changes.
     */
    public function verifyCallback(Request $request): ?CallbackResult
    {
        return null;
    }

    public function confirmManually(Payment $payment, ?string $reference): CallbackResult
    {
        return new CallbackResult(
            provider: $this->key(),
            providerReference: $reference ?? 'payat_go-'.$payment->id,
            status: PaymentStatus::SETTLED,
            amountCents: $payment->amount_cents,
            raw: ['confirmed_by_user_id' => $payment->confirmed_by],
        );
    }
}
