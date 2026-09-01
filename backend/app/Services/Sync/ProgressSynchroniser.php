<?php

declare(strict_types=1);

namespace App\Services\Sync;

use App\Models\Learner;
use App\Models\LearnerEvidence;
use App\Models\LearnerSubmission;
use App\Models\LearnerSubStep;
use App\Models\Programme;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Merging a device's batch into the learner's record.
 *
 * Every rule here exists because two devices can both be right. A learner
 * works on her phone on the taxi with no signal, works at KCS on a lab PC in
 * the afternoon, and both push later. Neither copy is stale in any way the
 * other can see, so "last write wins" would silently throw away a morning.
 *
 * The rules, and why:
 *
 * - **Completion is a ratchet.** A sub-step that is complete anywhere is
 *   complete, and the earliest completion time is the one kept. This is not
 *   last-write-wins on a timestamp, deliberately: half the lab PCs have a
 *   clock nobody has ever set, and a wrong clock must not be able to erase
 *   work. The cost is that un-ticking a step needs the learner to do it while
 *   online; losing an afternoon's work costs more.
 *
 * - **Submissions merge, they do not replace.** The latest `submitted_at`
 *   wins — a resubmission is newer and should show — and a rating is never
 *   overwritten with nothing.
 *
 * - **Competence is not in the upsert at all.** `result`, `assessed_at`,
 *   `assessed_by` and `feedback` are absent from the update column list, so
 *   no device can move them however the request is shaped. That is the
 *   backend's half of "the app reports what the learner did; we decide what
 *   it counts for".
 *
 * - **Replays are free.** Every rule above is idempotent, so a client that
 *   cannot tell whether its last push landed can simply push again.
 */
class ProgressSynchroniser
{
    /**
     * @param  array{sub_steps?: array<int, array<string, mixed>>, submissions?: array<int, array<string, mixed>>}  $batch
     */
    public function push(Learner $learner, Programme $programme, array $batch, ?string $device): void
    {
        DB::transaction(function () use ($learner, $programme, $batch, $device): void {
            $this->mergeSubSteps($learner, $programme, $batch['sub_steps'] ?? [], $device);
            $this->mergeSubmissions($learner, $programme, $batch['submissions'] ?? [], $device);
        });
    }

    /**
     * The authoritative record for one learner on one programme — what any
     * device should hold after a sync, and what a brand new device is handed
     * the first time it logs in.
     *
     * @return array{sub_steps: Collection<int, LearnerSubStep>, submissions: Collection<int, LearnerSubmission>, evidence: Collection<int, LearnerEvidence>}
     */
    public function record(Learner $learner, Programme $programme): array
    {
        $scope = fn (string $model) => $model::query()
            ->where('learner_id', $learner->id)
            ->where('programme_id', $programme->id);

        return [
            'sub_steps' => $scope(LearnerSubStep::class)->orderBy('sub_step_id')->get(),
            'submissions' => $scope(LearnerSubmission::class)->orderBy('task_id')->get(),
            'evidence' => $scope(LearnerEvidence::class)->orderBy('received_at')->orderBy('id')->get(),
        ];
    }

    // ------------------------------------------------------------- sub-steps

    /** @param  array<int, array<string, mixed>>  $rows */
    private function mergeSubSteps(Learner $learner, Programme $programme, array $rows, ?string $device): void
    {
        if ($rows === []) {
            return;
        }

        // One id mentioned twice in a batch is one thing, not two. The last
        // mention is the device's final word on it.
        $incoming = [];
        foreach ($rows as $row) {
            $incoming[(string) $row['sub_step_id']] = $row;
        }

        $existing = LearnerSubStep::query()
            ->where('learner_id', $learner->id)
            ->where('programme_id', $programme->id)
            ->whereIn('sub_step_id', array_keys($incoming))
            ->get()
            ->keyBy('sub_step_id');

        $now = Carbon::now();
        $values = [];

        foreach ($incoming as $subStepId => $row) {
            $prior = $existing->get($subStepId);

            $rowComplete = (bool) ($row['complete'] ?? false);
            $priorComplete = (bool) ($prior?->complete);
            $complete = $rowComplete || $priorComplete;

            $completedAt = $complete
                ? $this->earliest(
                    $priorComplete ? $prior?->completed_at : null,
                    $rowComplete ? ($this->time($row['completed_at'] ?? null) ?? $now) : null,
                )
                : null;

            $values[] = [
                'learner_id' => $learner->id,
                'programme_id' => $programme->id,
                'sub_step_id' => $subStepId,
                'task_id' => (string) ($row['task_id'] ?? $prior?->task_id ?? ''),
                'complete' => $complete,
                'completed_at' => $completedAt,
                'client_updated_at' => $this->latest(
                    $prior?->client_updated_at,
                    $this->time($row['client_updated_at'] ?? null),
                ),
                'last_device' => $device ?? $prior?->last_device,
                'created_at' => $prior?->created_at ?? $now,
                'updated_at' => $now,
            ];
        }

        LearnerSubStep::upsert(
            $values,
            ['learner_id', 'programme_id', 'sub_step_id'],
            ['task_id', 'complete', 'completed_at', 'client_updated_at', 'last_device', 'updated_at'],
        );
    }

    // ----------------------------------------------------------- submissions

    /** @param  array<int, array<string, mixed>>  $rows */
    private function mergeSubmissions(Learner $learner, Programme $programme, array $rows, ?string $device): void
    {
        if ($rows === []) {
            return;
        }

        $incoming = [];
        foreach ($rows as $row) {
            $incoming[(string) $row['task_id']] = $row;
        }

        $existing = LearnerSubmission::query()
            ->where('learner_id', $learner->id)
            ->where('programme_id', $programme->id)
            ->whereIn('task_id', array_keys($incoming))
            ->get()
            ->keyBy('task_id');

        $now = Carbon::now();
        $values = [];

        foreach ($incoming as $taskId => $row) {
            $prior = $existing->get($taskId);
            $rowUpdatedAt = $this->time($row['client_updated_at'] ?? null);

            $values[] = [
                'learner_id' => $learner->id,
                'programme_id' => $programme->id,
                'task_id' => $taskId,
                // A resubmission is a later hand-in of the same task, so the
                // newest wins — but a batch that simply does not mention a
                // submission time never erases one we already hold.
                'submitted_at' => $this->latest(
                    $prior?->submitted_at,
                    $this->time($row['submitted_at'] ?? null),
                ),
                'confidence_rating' => $this->mergeConfidence($prior, $row, $rowUpdatedAt),
                'client_updated_at' => $this->latest($prior?->client_updated_at, $rowUpdatedAt),
                'last_device' => $device ?? $prior?->last_device,
                'created_at' => $prior?->created_at ?? $now,
                'updated_at' => $now,
            ];
        }

        LearnerSubmission::upsert(
            $values,
            ['learner_id', 'programme_id', 'task_id'],
            // `result`, `assessed_at`, `assessed_by` and `feedback` are
            // absent on purpose. Leaving them out of this list is what makes
            // "a device cannot write competence" structural rather than a
            // rule someone has to remember at the next endpoint.
            [...LearnerSubmission::DEVICE_WRITABLE, 'updated_at'],
        );
    }

    /**
     * A self-reported confidence rating. Never overwritten with nothing, and
     * never overwritten by a device whose own clock says it is older than
     * the rating we already hold.
     *
     * @param  array<string, mixed>  $row
     */
    private function mergeConfidence(?LearnerSubmission $prior, array $row, ?CarbonInterface $rowUpdatedAt): ?int
    {
        $incoming = $row['confidence_rating'] ?? null;

        if ($incoming === null) {
            return $prior?->confidence_rating;
        }

        $held = $prior?->confidence_rating;

        if ($held !== null
            && $rowUpdatedAt !== null
            && $prior?->client_updated_at !== null
            && $rowUpdatedAt->lt($prior->client_updated_at)) {
            return $held;
        }

        return (int) $incoming;
    }

    // ---------------------------------------------------------------- clocks

    /**
     * A time as the client wrote it, in the timezone we store in.
     *
     * The API takes ISO 8601, so one device sends `08:00:00+02:00` and
     * another sends `06:00:00Z` for the same instant. Eloquent stores a
     * Carbon in whatever offset it is carrying, so without this the two land
     * in the column two hours apart and every "earliest wins" comparison
     * between them is wrong.
     */
    private function time(mixed $value): ?CarbonInterface
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse((string) $value)->setTimezone(config('app.timezone') ?: 'UTC');
    }

    private function earliest(?CarbonInterface ...$times): ?CarbonInterface
    {
        return $this->pick(fn (CarbonInterface $a, CarbonInterface $b): bool => $a->lt($b), ...$times);
    }

    private function latest(?CarbonInterface ...$times): ?CarbonInterface
    {
        return $this->pick(fn (CarbonInterface $a, CarbonInterface $b): bool => $a->gt($b), ...$times);
    }

    private function pick(callable $beats, ?CarbonInterface ...$times): ?CarbonInterface
    {
        $chosen = null;

        foreach ($times as $time) {
            if ($time === null) {
                continue;
            }
            if ($chosen === null || $beats($time, $chosen)) {
                $chosen = $time;
            }
        }

        return $chosen;
    }
}
