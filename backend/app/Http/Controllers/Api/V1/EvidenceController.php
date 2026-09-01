<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\EvidenceResource;
use App\Models\Learner;
use App\Services\Sync\EvidenceVault;
use App\Services\Sync\SyncScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Evidence in and out.
 *
 * Separate from the progress push on purpose. Progress is a few kilobytes of
 * JSON that must land the moment a line appears; evidence is megabytes that
 * can take their time. Draining them at different rates is what stops a
 * learner's afternoon of work being held up behind one photo.
 */
class EvidenceController extends Controller
{
    public function __construct(
        private readonly SyncScope $scope,
        private readonly EvidenceVault $vault,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $maxKilobytes = (int) ceil(((int) config('sync.evidence.max_bytes')) / 1024);
        $extensions = implode(',', (array) config('sync.evidence.extensions', []));

        $data = $request->validate([
            'programme' => ['nullable', 'string', 'max:16'],
            'device' => ['nullable', 'string', 'max:120'],
            'client_evidence_id' => ['required', 'string', 'max:64'],
            'task_id' => ['required', 'string', 'max:120'],
            'file' => ['required', 'file', 'max:'.$maxKilobytes, 'mimes:'.$extensions],
            'description' => ['nullable', 'string', 'max:500'],
            'captured_at' => ['nullable', 'date'],
        ]);

        /** @var Learner $learner */
        $learner = $request->user();

        $entitlement = $this->scope->resolve($learner, $data['programme'] ?? null);

        $result = $this->vault->store(
            learner: $learner,
            programme: $entitlement->programme,
            file: $request->file('file'),
            meta: [
                'client_evidence_id' => $data['client_evidence_id'],
                'task_id' => $data['task_id'],
                'description' => $data['description'] ?? null,
                'captured_at' => $data['captured_at'] ?? null,
            ],
            device: $data['device'] ?? null,
        );

        return response()->json(
            ['evidence' => new EvidenceResource($result['evidence'])],
            // 200 rather than 201 says "you already sent me this" without
            // making a retry look like a failure.
            $result['created'] ? 201 : 200,
        );
    }

    /**
     * Hand a file back. Scoped to the caller's own evidence, so another
     * learner's id is a 404 rather than a permission message that confirms
     * the file exists.
     */
    public function show(Request $request, string $evidence): StreamedResponse
    {
        /** @var Learner $learner */
        $learner = $request->user();

        $record = $learner->evidence()
            ->where('client_evidence_id', $evidence)
            ->firstOrFail();

        return Storage::disk($record->disk)->download($record->storage_path, $record->file_name);
    }
}
