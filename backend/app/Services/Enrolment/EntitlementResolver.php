<?php

declare(strict_types=1);

namespace App\Services\Enrolment;

use App\Enums\EnrolmentStatus;
use App\Enums\EntitlementState;
use App\Enums\RequirementRule;
use App\Models\Enrolment;
use App\Models\Entitlement;
use App\Models\Learner;
use App\Models\Programme;
use Illuminate\Support\Carbon;

/**
 * Answers one question for one learner: what may they open right now.
 *
 * Recomputed rather than incremented, so it can never drift from the
 * enrolments underneath it. When the Android app arrives in Phase 3 this is
 * the only table it reads to decide what to show — which is why the answer
 * has to be derivable from scratch at any moment.
 */
class EntitlementResolver
{
    public function resolveFor(Learner $learner): void
    {
        $enrolments = $learner->enrolments()->get()->keyBy('programme_id');

        foreach (Programme::all() as $programme) {
            $enrolment = $enrolments->get($programme->id);

            $state = match (true) {
                $enrolment?->status === EnrolmentStatus::COMPLETED => EntitlementState::COMPLETED,
                $enrolment?->status === EnrolmentStatus::ACTIVE => EntitlementState::ACTIVE,
                $enrolment?->status === EnrolmentStatus::EXPIRED => EntitlementState::EXPIRED,
                $this->isAvailable($programme, $learner) => EntitlementState::AVAILABLE,
                default => EntitlementState::LOCKED,
            };

            $entitlement = Entitlement::firstOrNew([
                'learner_id' => $learner->id,
                'programme_id' => $programme->id,
            ]);

            // Only stamp the unlock moment the first time, so a recompute does
            // not keep resetting when a learner gained access.
            $becomingOpen = in_array($state, [EntitlementState::ACTIVE, EntitlementState::AVAILABLE], true);

            $entitlement->fill([
                'state' => $state,
                'source_enrolment_id' => $enrolment?->id,
                'unlocked_at' => $entitlement->unlocked_at ?? ($becomingOpen ? now() : null),
                'expires_at' => $this->expiryFor($enrolment),
                'reason' => $this->reasonFor($state, $programme),
            ])->save();
        }
    }

    /**
     * When access runs out.
     *
     * A fixed-duration block is the reason this exists: R950 buys ninety days,
     * and the day the ninety days start is the day the enrolment activates —
     * not the day the learner applied and not the day they first opened the
     * app. An offering with no duration grants open-ended access.
     */
    private function expiryFor(?Enrolment $enrolment): ?Carbon
    {
        $days = $enrolment?->offering?->access_duration_days;
        $from = $enrolment?->activated_at;

        if ($days === null || $from === null) {
            return null;
        }

        return $from->copy()->addDays($days);
    }

    /**
     * Whether a learner may buy into this programme at all.
     *
     * A Professional Specialisation requires certification in its prerequisite
     * — a moderated human decision — not merely having finished the tasks.
     * Gating on completion would let someone click through a programme into a
     * paid specialisation they have not been assessed for.
     */
    private function isAvailable(Programme $programme, Learner $learner): bool
    {
        $requirement = $programme->requirements()->first();
        $rule = $requirement?->rule_type ?? RequirementRule::NONE;

        return match ($rule) {
            RequirementRule::NONE => true,

            RequirementRule::COMPLETED_PROGRAMME => $this->hasCompleted(
                $learner,
                $requirement?->requires_programme_id,
            ),

            // Certification does not exist until Phase 5, so this is correctly
            // false today rather than optimistically true. A specialisation
            // that cannot yet be earned must not be advertised as available.
            RequirementRule::CERTIFIED_IN_PROGRAMME => false,

            // Both need a person to decide, so neither is ever automatic.
            RequirementRule::RPL, RequirementRule::MANUAL_APPROVAL => false,
        };
    }

    private function hasCompleted(Learner $learner, ?int $programmeId): bool
    {
        if ($programmeId === null) {
            return false;
        }

        return $learner->enrolments()
            ->where('programme_id', $programmeId)
            ->where('status', EnrolmentStatus::COMPLETED)
            ->exists();
    }

    private function reasonFor(EntitlementState $state, Programme $programme): ?string
    {
        return match ($state) {
            EntitlementState::ACTIVE => 'Enrolled and paid.',
            EntitlementState::COMPLETED => 'Programme completed.',
            EntitlementState::AVAILABLE => 'Open for application.',
            EntitlementState::LOCKED => 'Entry requirements not yet met.',
            EntitlementState::EXPIRED => 'Enrolment expired.',
        };
    }
}
