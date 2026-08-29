<?php

declare(strict_types=1);

namespace App\Services\Enrolment;

use App\Enums\EnrolmentStatus;
use App\Enums\EntitlementState;
use App\Enums\RequirementRule;
use App\Models\Entitlement;
use App\Models\Learner;
use App\Models\Programme;

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
                $this->isAvailable($programme) => EntitlementState::AVAILABLE,
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
                'reason' => $this->reasonFor($state, $programme),
            ])->save();
        }
    }

    /**
     * Phase 1 records the rules but does not gate on them: nothing has been
     * completed yet, so enforcing a prerequisite chain would only ever produce
     * false negatives. Enforcement arrives in Phase 5 with completion.
     */
    private function isAvailable(Programme $programme): bool
    {
        $rule = $programme->requirements()->first()?->rule_type ?? RequirementRule::NONE;

        return $rule === RequirementRule::NONE;
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
