<?php

declare(strict_types=1);

namespace App\Services\Registration;

use App\Models\Learner;
use Illuminate\Support\Facades\URL;

/**
 * The private link a learner uses to finish their own registration.
 *
 * Signed rather than a stored token, because the guarantees come for free and
 * there is no table to keep clean: the signature is verified against the app
 * key, the expiry is inside the link, and a tampered learner id fails the
 * check. Nothing about the learner is exposed by the URL itself.
 *
 * Links expire deliberately. A profile link that works forever is a
 * credential sitting in a WhatsApp thread, and a payment link that never
 * lapses gives a prospect no reason to decide.
 */
class LearnerLinks
{
    /** How long a learner has to finish their profile before the link lapses. */
    public const PROFILE_DAYS = 30;

    /**
     * How long a learner has to pick their workspace PIN.
     *
     * Longer than a password reset because this arrives at the same moment as
     * a payment receipt and a lot else, and a learner who opens it a week
     * later must not be locked out of the course they have paid for.
     */
    public const ACCESS_DAYS = 21;

    public function profile(Learner $learner, ?int $days = null): string
    {
        return URL::temporarySignedRoute(
            'learner.profile.show',
            now()->addDays($days ?? self::PROFILE_DAYS),
            ['learner' => $learner->id],
        );
    }

    /**
     * Where a paid learner chooses the PIN they will use at a lab computer.
     *
     * A link rather than a PIN in the email body. Emailing "your number is X
     * and your PIN is Y" puts a whole working credential in an inbox, a
     * forwarded message and a WhatsApp thread; a signed link expires, works
     * once the learner has used it, and leaves the secret something only they
     * have ever typed.
     */
    public function workspaceAccess(Learner $learner, ?int $days = null): string
    {
        return URL::temporarySignedRoute(
            'learner.access.show',
            now()->addDays($days ?? self::ACCESS_DAYS),
            ['learner' => $learner->id],
        );
    }

    /** The same link as it goes into an email. */
    public function friendlyWorkspaceAccess(Learner $learner, ?int $days = null): string
    {
        return $this->onPublicHost($this->workspaceAccess($learner, $days));
    }

    /**
     * The same link, shortened for a message.
     *
     * The app is served from /admin, so a raw signed URL reads like a staff
     * area to somebody who was sent it — which is exactly the sort of link
     * people do not click. The website rewrites /my/... onto it.
     */
    public function friendlyProfile(Learner $learner, ?int $days = null): string
    {
        return $this->onPublicHost($this->profile($learner, $days));
    }

    /**
     * Everything from /my onward — path, expiry and signature — hung off the
     * public host. Rebuilt rather than string-replaced on config, because the
     * scheme and base path the app generates are not guaranteed to match what
     * APP_URL says.
     *
     * Note what this does to the signature: it is computed over the whole URL,
     * so moving the host invalidates it. The link works because the website
     * forwards /my/... straight back to the address it was signed for — which
     * means these are the form to SEND, and never the form to test against.
     * Sign with the plain method, send with this one.
     */
    private function onPublicHost(string $signed): string
    {
        $position = strpos($signed, '/my/');

        return $position === false
            ? $signed
            : rtrim((string) config('kcs.public_url'), '/').substr($signed, $position);
    }
}
