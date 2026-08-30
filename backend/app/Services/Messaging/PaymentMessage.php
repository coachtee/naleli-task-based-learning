<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Enums\PayAtAccountState;
use App\Models\Invoice;
use App\Support\Normalise;

/**
 * The message a registrar sends a learner to tell them what to pay.
 *
 * This exists because the website promises "we will send your registration
 * reference and payment options to your WhatsApp shortly" and, until now,
 * nothing did. A payable reference sitting in the dashboard that the learner
 * has never seen cannot be paid, so the whole flow stopped here.
 *
 * It composes the text rather than sending it: WhatsApp Business API needs an
 * account, a per-message rate and template approval, and KCS staff already
 * message learners by hand. One click opens WhatsApp with the message written,
 * which removes the copy-and-paste step where a wrong reference gets sent
 * without adding a provider, a cost or a dependency.
 */
class PaymentMessage
{
    /**
     * Null when there is nothing safe to send: no payable reference, or a
     * reference Pay@ has already closed. Sending a learner a dead number is
     * worse than sending nothing.
     */
    public function forInvoice(Invoice $invoice): ?string
    {
        if ($invoice->payat_source_reference === null) {
            return null;
        }

        if (PayAtAccountState::tryFromApi($invoice->payat_state)?->isClosed() === true) {
            return null;
        }

        $learner = $invoice->learner;
        $programme = $invoice->enrolment?->programme?->name;
        $amount = 'R'.number_format($invoice->amount_cents / 100, 2);
        $name = $learner?->preferred_name ?: $learner?->first_name;

        $lines = [
            "Hi {$name},",
            '',
        ];

        $lines[] = $invoice->activates_enrolment
            ? 'Your registration'.($programme !== null ? " for {$programme}" : '').
              ' at Katlehong Computer School has started.'
            : "Here is your next payment for {$programme} at Katlehong Computer School.";

        $lines[] = "Your student reference is {$learner?->learner_ref}.";
        $lines[] = '';
        $lines[] = "{$invoice->description}: {$amount}";
        $lines[] = '';
        $lines[] = 'Pay cash at any Pay@ till — Shoprite, Checkers, Pick n Pay, Boxer or USave —';
        $lines[] = "using this reference: {$invoice->payat_source_reference}";

        if ($invoice->payat_payment_link !== null) {
            $lines[] = '';
            $lines[] = "Or pay from your phone: {$invoice->payat_payment_link}";
        }

        if ($invoice->activates_enrolment) {
            $lines[] = '';
            $lines[] = 'Your place is confirmed as soon as this payment reaches us, and we will '.
                'send you your access details straight after.';
        }

        $lines[] = '';
        $lines[] = 'Katlehong Computer School';

        return implode("\n", $lines);
    }

    /**
     * A wa.me link that opens the chat with the message already written.
     *
     * Null when the learner has no usable mobile number, so the dashboard hides
     * the button rather than opening a chat with nobody.
     */
    public function whatsAppLinkFor(Invoice $invoice): ?string
    {
        $message = $this->forInvoice($invoice);

        if ($message === null) {
            return null;
        }

        $learner = $invoice->learner;
        $number = Normalise::whatsappNumber($learner?->whatsapp)
            ?? Normalise::whatsappNumber($learner?->phone);

        if ($number === null) {
            return null;
        }

        return "https://wa.me/{$number}?text=".rawurlencode($message);
    }
}
