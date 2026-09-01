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

    public function profile(Learner $learner, ?int $days = null): string
    {
        return URL::temporarySignedRoute(
            'learner.profile.show',
            now()->addDays($days ?? self::PROFILE_DAYS),
            ['learner' => $learner->id],
        );
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
        $signed = $this->profile($learner, $days);
        $position = strpos($signed, '/my/');

        // Everything from /my onward — path, expiry and signature — hung off
        // the public host. Rebuilt rather than string-replaced on config,
        // because the scheme and base path the app generates are not
        // guaranteed to match what APP_URL says.
        return $position === false
            ? $signed
            : rtrim((string) config('kcs.public_url'), '/').substr($signed, $position);
    }
}
