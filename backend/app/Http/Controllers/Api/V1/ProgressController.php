<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\EvidenceResource;
use App\Http\Resources\SubmissionResource;
use App\Http\Resources\SubStepResource;
use App\Models\Entitlement;
use App\Models\Learner;
use App\Services\Sync\ProgressSynchroniser;
use App\Services\Sync\SyncScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The learner's record, in and out.
 *
 * The account owns the work; a phone and a lab PC are working copies. So
 * `show` hands a device the whole record, and `store` merges a device's
 * batch and hands back the whole record again — the same shape, deliberately.
 * A client never has to reason about what its push did: it replaces its local
 * state with the response and it is correct, including the corrections the
 * merge applied to what it just sent.
 */
class ProgressController extends Controller
{
    public function __construct(
        private readonly SyncScope $scope,
        private readonly ProgressSynchroniser $sync,
    ) {}

    public function show(Request $request): JsonResponse
    {
        /** @var Learner $learner */
        $learner = $request->user();

        $entitlement = $this->scope->resolve($learner, $request->query('programme'));

        return $this->record($entitlement, $learner);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'programme' => ['nullable', 'string', 'max:16'],
            'device' => ['nullable', 'string', 'max:120'],

            'sub_steps' => ['array', 'max:'.config('sync.max_sub_steps')],
            'sub_steps.*.sub_step_id' => ['required', 'string', 'max:120'],
            'sub_steps.*.task_id' => ['required', 'string', 'max:120'],
            'sub_steps.*.complete' => ['required', 'boolean'],
            'sub_steps.*.completed_at' => ['nullable', 'date'],
            'sub_steps.*.client_updated_at' => ['nullable', 'date'],

            'submissions' => ['array', 'max:'.config('sync.max_submissions')],
            'submissions.*.task_id' => ['required', 'string', 'max:120'],
            'submissions.*.submitted_at' => ['nullable', 'date'],
            'submissions.*.confidence_rating' => ['nullable', 'integer', 'between:1,5'],
            'submissions.*.client_updated_at' => ['nullable', 'date'],
        ]);

        // Note what is not in that list: `result`, `assessed_at`, `feedback`.
        // A client may send them; validate() drops them here, and the
        // synchroniser's upsert would not write them anyway. Two independent
        // reasons, because this is the rule the whole qualification rests on.

        /** @var Learner $learner */
        $learner = $request->user();

        $entitlement = $this->scope->resolve($learner, $data['programme'] ?? null);

        $this->sync->push(
            learner: $learner,
            programme: $entitlement->programme,
            batch: $data,
            device: $data['device'] ?? null,
        );

        return $this->record($entitlement, $learner);
    }

    private function record(Entitlement $entitlement, Learner $learner): JsonResponse
    {
        $programme = $entitlement->programme;
        $record = $this->sync->record($learner, $programme);

        return response()->json([
            // The device's own clock is not trusted for merging, so give it
            // ours: a client that is hours out can at least say so.
            'server_time' => now()->toIso8601String(),
            'programme' => [
                'code' => $programme->code,
                'name' => $programme->name,
                'content_code' => $programme->content_code,
                'content_version' => $programme->content_version,
                'entitlement_state' => $entitlement->state->value,
                'expires_at' => $entitlement->expires_at?->toIso8601String(),
            ],
            'sub_steps' => SubStepResource::collection($record['sub_steps']),
            'submissions' => SubmissionResource::collection($record['submissions']),
            'evidence' => EvidenceResource::collection($record['evidence']),
        ]);
    }
}
