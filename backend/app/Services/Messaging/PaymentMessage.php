<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Enums\PayAtAccountState;
use App\Models\Invoice;
use App\Models\Learner;
use App\Services\Registration\LearnerLinks;
use App\Services\Registration\ProfileCompleteness;
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

    /**
     * The message asking a learner to finish their own registration.
     *
     * Names what is actually outstanding rather than saying "complete your
     * profile", because a person is far likelier to open a link that tells
     * them it wants their ID and their address than one that does not.
     */
    public function profileMessage(Learner $learner): ?string
    {
        $profiles = app(ProfileCompleteness::class);

        if ($profiles->isComplete($learner)) {
            return null;
        }

        $missing = $profiles->missing($learner);
        $name = $learner->preferred_name ?: $learner->first_name;

        return implode("\n", [
            "Hi {$name},",
            '',
            'Your place at Katlehong Computer School is confirmed. There are a few details '
                .'we still need before your first block starts.',
            '',
            'Still outstanding: '.strtolower(implode(', ', $missing)).'.',
            '',
            'Fill them in here — it takes about three minutes and you can come back to it:',
            app(LearnerLinks::class)->friendlyProfile($learner),
            '',
            'The link is private to you and works for '.LearnerLinks::PROFILE_DAYS.' days.',
            '',
            'Katlehong Computer School',
        ]);
    }

    /** Null when there is nothing outstanding, or no number to send it to. */
    /**
     * "Your course is open" for WhatsApp.
     *
     * Same content as the email and the same rule: the link, never the PIN.
     * Sent as well as the email rather than instead of it — a learner who
     * gave us a Gmail address they check monthly still reads WhatsApp today.
     */
    public function workspaceAccessMessage(Learner $learner, string $link): string
    {
        $name = $learner->preferred_name ?: $learner->first_name;
        $workspace = rtrim((string) config('kcs.workspace_url') ?: url('/workspace'), '/');

        return implode("\n\n", [
            "Hi {$name}, your payment is in and your KCS course is open.",
            "Your student number is {$learner->learner_ref} — you will type it every time you sign in.",
            "First, choose your PIN here: {$link}",
            "Then sign in at {$workspace} on any computer at KCS, or on your phone.",
            'Keep your PIN to yourself — the lab computers are shared.',
        ]);
    }

    public function workspaceAccessWhatsAppLink(Learner $learner, string $link): ?string
    {
        $number = Normalise::whatsappNumber($learner->whatsapp)
            ?? Normalise::whatsappNumber($learner->phone);

        return $number === null
            ? null
            : "https://wa.me/{$number}?text=".rawurlencode($this->workspaceAccessMessage($learner, $link));
    }

    /**
     * The first message to somebody who tapped a Facebook ad.
     *
     * Written to be sent by a person, not to look like a broadcast: it says
     * who is writing, what they asked about, and asks one question. A lead who
     * gets a template does not reply.
     */
    public function leadIntroMessage(Learner $learner, ?string $sender = null): string
    {
        $name = $learner->preferred_name ?: $learner->first_name;
        $from = $sender !== null && $sender !== '' ? " This is {$sender} from KCS." : ' This is KCS.';

        return implode("\n\n", [
            "Hi {$name},{$from}",
            'You asked about our courses on Facebook — thank you.',
            'We run a 3-month Digital Foundation block: R500 to register, then R950 a month. '
                .'You learn by doing real workplace tasks, not just theory.',
            'Can I answer anything, or shall I send you the registration link?',
        ]);
    }

    public function leadIntroWhatsAppLink(Learner $learner, ?string $sender = null): ?string
    {
        $number = Normalise::whatsappNumber($learner->whatsapp)
            ?? Normalise::whatsappNumber($learner->phone);

        return $number === null
            ? null
            : "https://wa.me/{$number}?text=".rawurlencode($this->leadIntroMessage($learner, $sender));
    }

    public function profileWhatsAppLink(Learner $learner): ?string
    {
        $message = $this->profileMessage($learner);

        if ($message === null) {
            return null;
        }

        $number = Normalise::whatsappNumber($learner->whatsapp)
            ?? Normalise::whatsappNumber($learner->phone);

        return $number === null ? null : "https://wa.me/{$number}?text=".rawurlencode($message);
    }
}
