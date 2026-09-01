<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Enrolment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * "Your course is open."
 *
 * The message that used to not exist. A learner paid, the system activated
 * their enrolment and issued an access token for the phone app — and nobody
 * ever told them how to get in. The registrar read a token off a screen and
 * the rest was word of mouth.
 *
 * It carries a link, not a PIN. Emailing "your number is X and your PIN is Y"
 * puts a whole working credential into an inbox, a forwarded message and a
 * WhatsApp thread that outlive the course; a signed link expires, and leaves
 * the secret something only the learner has ever typed.
 */
class CourseAccessOpened extends Mailable
{
    public function __construct(
        public readonly Enrolment $enrolment,
        public readonly string $accessLink,
        public readonly ?string $appToken = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your course is open — '.$this->enrolment->learner->learner_ref,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.course-access-opened',
            with: [
                'learner' => $this->enrolment->learner,
                'programme' => $this->enrolment->programme,
                'link' => $this->accessLink,
                'appToken' => $this->appToken,
                'workspace' => rtrim(
                    (string) config('kcs.workspace_url') ?: url('/workspace'),
                    '/',
                ),
            ],
        );
    }
}
