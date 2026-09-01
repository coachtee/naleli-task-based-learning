<?php

declare(strict_types=1);

namespace App\Services\Leads;

use App\Enums\ApplicationStatus;
use App\Enums\TouchChannel;
use App\Enums\TouchOutcome;
use App\Models\Application;
use App\Models\LeadTouch;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Recording a call, and deciding when the next one is due.
 *
 * One place, because "when do I chase this again" is the rule the whole queue
 * is built on and it must not be re-invented at each button. The outcome
 * decides the interval — a no-answer comes back in two days, an "interested
 * but not this intake" in six weeks, and a "not interested" leaves the queue
 * rather than being skipped every morning for ever.
 */
class TouchLog
{
    public function record(
        Application $application,
        TouchChannel $channel,
        TouchOutcome $outcome,
        ?string $note = null,
        ?User $by = null,
        ?Carbon $nextActionAt = null,
    ): LeadTouch {
        return DB::transaction(function () use ($application, $channel, $outcome, $note, $by, $nextActionAt): LeadTouch {
            $now = Carbon::now();

            $touch = LeadTouch::create([
                'application_id' => $application->id,
                'user_id' => $by?->id,
                'channel' => $channel,
                'outcome' => $outcome,
                'note' => $note,
                'occurred_at' => $now,
            ]);

            $days = $outcome->nextActionInDays();

            $application->forceFill([
                'last_touched_at' => $now,
                'touch_count' => $application->touch_count + 1,
                // Stamped once. "How long did we take to reach them the first
                // time" is the number that predicts whether we ever will.
                'first_contacted_at' => $application->first_contacted_at ?? $now,
                'next_action_at' => $nextActionAt
                    ?? ($days === null ? null : $now->copy()->addDays($days)),
                'owner_id' => $application->owner_id ?? $by?->id,
            ]);

            // A lead we have actually spoken to is no longer a new lead. Only
            // ever forward: a later call does not un-register somebody who
            // registered in between.
            if ($application->status === ApplicationStatus::LEAD) {
                $application->status = ApplicationStatus::CONTACTED;
            }

            if ($outcome->closesTheLead() && $this->isStillALead($application)) {
                $application->status = ApplicationStatus::WITHDRAWN;
            }

            $application->save();

            return $touch;
        });
    }

    /** Nothing here may touch somebody who has moved past the chasing stage. */
    private function isStillALead(Application $application): bool
    {
        return in_array($application->status, [
            ApplicationStatus::LEAD,
            ApplicationStatus::CONTACTED,
        ], true);
    }
}
