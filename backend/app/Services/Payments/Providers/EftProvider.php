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
 * A bank transfer into the school account.
 *
 * Also already working, and the reference is the bank statement line. Kept
 * distinct from Pay@ Go and from over-the-counter cash so finance can tell
 * the three apart when reconciling.
 */
class EftProvider implements PaymentProvider
{
    public function key(): string
    {
        return 'eft';
    }

    public function label(): string
    {
        return 'EFT / bank transfer';
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
     * No callback: settlement is a person reading the bank statement and confirming it
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
            providerReference: $reference ?? 'eft-'.$payment->id,
            status: PaymentStatus::SETTLED,
            amountCents: $payment->amount_cents,
            raw: ['confirmed_by_user_id' => $payment->confirmed_by],
        );
    }
}
