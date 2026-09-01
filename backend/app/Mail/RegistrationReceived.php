<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Application;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * "We have your registration."
 *
 * The gap this closes was found by the school's own director registering on
 * their own website and hearing nothing back. A prospect who submits a form
 * into silence has no evidence the school is running, and the next thing they
 * do is look at somebody else's — so this goes out the moment a form lands,
 * before any human has touched it.
 *
 * It promises only what the system can keep: the reference is real, the fee is
 * read off the offering, and payment details follow when a registrar issues
 * them. No invented timelines.
 */
class RegistrationReceived extends Mailable
{
    public function __construct(public readonly Application $application) {}

    public function envelope(): Envelope
    {
        $ref = $this->application->learner->learner_ref;

        return new Envelope(
            subject: "We have your registration — {$ref}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.registration-received',
            with: [
                'learner' => $this->application->learner,
                'programme' => $this->application->programme,
            ],
        );
    }
}
