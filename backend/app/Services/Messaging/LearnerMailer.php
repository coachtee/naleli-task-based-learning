<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Mail\RegistrationReceived;
use App\Models\Application;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Every email the school sends a learner, and the one rule that governs them:
 * a message that fails to send must never take the thing it was announcing
 * down with it.
 *
 * A registration is money. If the mail server is unreachable, or a credential
 * has expired, or Google is rate-limiting, the learner record still gets
 * created and the registrar still sees it — the failure is logged for somebody
 * to look at, and the flow carries on. That is why nothing here throws.
 */
class LearnerMailer
{
    /** No credentials configured means no attempt, rather than an exception per registration. */
    public function isConfigured(): bool
    {
        return (string) config('mail.mailers.smtp.username') !== ''
            && (string) config('mail.mailers.smtp.password') !== '';
    }

    public function registrationReceived(Application $application): bool
    {
        $email = $application->learner?->email;

        if ($email === null || $email === '') {
            return false;
        }

        return $this->send($email, new RegistrationReceived($application), [
            'mail' => 'registration_received',
            'learner_ref' => $application->learner->learner_ref,
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function send(string $to, Mailable $mailable, array $context): bool
    {
        if (! $this->isConfigured()) {
            // Worth a line in the log: the registration succeeded, the learner
            // heard nothing, and somebody should know which of those happened.
            Log::warning('Learner email skipped — no mail credentials configured.', $context);

            return false;
        }

        try {
            Mail::to($to)->send($mailable);

            return true;
        } catch (Throwable $e) {
            Log::error('Learner email failed to send.', $context + ['error' => $e->getMessage()]);
            report($e);

            return false;
        }
    }
}
