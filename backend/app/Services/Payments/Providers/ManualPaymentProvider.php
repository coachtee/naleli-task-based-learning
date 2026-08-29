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
 * A payment a person confirms: cash at reception, a bank transfer seen on a
 * statement, a card machine in the office.
 *
 * This is the only provider in Phase 1, and it does not go away when the
 * gateways arrive — cash and counter payments will not stop, and a system that
 * cannot record them pushes staff into a spreadsheet.
 */
class ManualPaymentProvider implements PaymentProvider
{
    public function key(): string
    {
        return 'manual';
    }

    public function label(): string
    {
        return 'Manual / EFT';
    }

    public function isRedirect(): bool
    {
        return false;
    }

    public function createCheckout(Invoice $invoice): ?CheckoutIntent
    {
        return null;
    }

    public function verifyCallback(Request $request): ?CallbackResult
    {
        return null;
    }

    public function confirmManually(Payment $payment, ?string $reference): CallbackResult
    {
        return new CallbackResult(
            provider: $this->key(),
            providerReference: $reference ?? "manual-{$payment->id}",
            status: PaymentStatus::SETTLED,
            amountCents: $payment->amount_cents,
            raw: ['confirmed_by_user_id' => $payment->confirmed_by],
        );
    }
}
