<?php

declare(strict_types=1);

namespace App\Services\Sync;

use App\Enums\EntitlementState;
use App\Models\Entitlement;
use App\Models\Learner;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Which programme a device is syncing, and whether it may.
 *
 * A learner can hold several entitlements, so every sync is scoped to one.
 * The client names it; if it does not, and the learner has exactly one it
 * could mean, we take that rather than making the common case say so.
 *
 * The permission rule is deliberately not "is the entitlement active". An
 * entitlement that has expired stops the learner opening new content — that
 * is the client's job, from `state` — but the work they already did is still
 * theirs, and a device that comes back online a week after expiry must be
 * able to hand it in. So writing is allowed for anything that has ever been
 * unlocked, and refused only for what was never opened at all.
 */
class SyncScope
{
    public function resolve(Learner $learner, ?string $programmeCode): Entitlement
    {
        $entitlements = $learner->entitlements()->with('programme')->get();

        if ($programmeCode !== null && $programmeCode !== '') {
            $entitlement = $entitlements->first(
                fn (Entitlement $e): bool => strcasecmp($e->programme->code, $programmeCode) === 0,
            );

            if ($entitlement === null) {
                throw ValidationException::withMessages([
                    'programme' => "This learner has no entitlement for programme {$programmeCode}.",
                ]);
            }

            return $this->assertSyncable($entitlement);
        }

        $syncable = $entitlements->filter(fn (Entitlement $e): bool => $this->hasBeenUnlocked($e))->values();

        if ($syncable->isEmpty()) {
            throw ValidationException::withMessages([
                'programme' => 'This learner has no programme open to sync.',
            ]);
        }

        if ($syncable->count() > 1) {
            $codes = $syncable->map(fn (Entitlement $e): string => $e->programme->code)->implode(', ');

            throw ValidationException::withMessages([
                'programme' => "This learner has more than one programme open ({$codes}). Name the one you are syncing.",
            ]);
        }

        return $syncable->first();
    }

    private function assertSyncable(Entitlement $entitlement): Entitlement
    {
        if (! $this->hasBeenUnlocked($entitlement)) {
            throw new AccessDeniedHttpException(
                "Programme {$entitlement->programme->code} has not been opened for this learner.",
            );
        }

        return $entitlement;
    }

    /**
     * Opened at some point, whatever it says now. `unlocked_at` is written
     * once, by EnrolmentActivator, and never cleared — so it is the honest
     * test of "did this learner ever have access", which expiry is not.
     */
    private function hasBeenUnlocked(Entitlement $entitlement): bool
    {
        return $entitlement->unlocked_at !== null
            && $entitlement->state !== EntitlementState::LOCKED;
    }
}
