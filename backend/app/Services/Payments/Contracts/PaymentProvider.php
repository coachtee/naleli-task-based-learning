<?php

declare(strict_types=1);

namespace App\Services\Payments\Contracts;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\Request;

/**
 * The seam every payment method plugs into.
 *
 * Merchant approval is uncertain — Ozow, PayJustNow and Payflex are all
 * applications in flight and any of them may be declined — so nothing above
 * this interface may know which provider settled a payment. Adding one is a
 * new class and a config line; the activation rule does not change.
 */
interface PaymentProvider
{
    /** Stored in payments.provider. Stable: never rename one in use. */
    public function key(): string;

    public function label(): string;

    /** Whether the learner is sent to the provider to pay. Manual and EFT are not. */
    public function isRedirect(): bool;

    /**
     * Start a payment. Redirect providers return a URL to send the learner to;
     * manual and EFT return null and are settled by a person.
     */
    public function createCheckout(Invoice $invoice): ?CheckoutIntent;

    /**
     * Interpret a callback. Returning null means "not mine, or not genuine" —
     * an unverified callback must never reach the activation rule.
     */
    public function verifyCallback(Request $request): ?CallbackResult;

    /** Confirm a payment out of band: a bank statement line, cash at reception. */
    public function confirmManually(Payment $payment, ?string $reference): CallbackResult;
}
