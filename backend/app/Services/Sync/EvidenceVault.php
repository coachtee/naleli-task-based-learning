<?php

declare(strict_types=1);

namespace App\Services\Sync;

use App\Models\Learner;
use App\Models\LearnerEvidence;
use App\Models\Programme;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Where a learner's evidence actually lands.
 *
 * Uploading is idempotent on the client's own `client_evidence_id`, which is
 * the only thing that makes evidence safe to sync over a bad line: a phone
 * that sent 4 MB and never saw the response can send it again, and gets the
 * row it already has instead of a second copy of the photo.
 *
 * Nothing the client sends is allowed to shape a path. The directory comes
 * from the learner reference we allocated, the file name is a UUID we
 * generate, and the extension is whitelisted — so a task id of "../../.env"
 * is just a slightly odd-looking folder name.
 */
class EvidenceVault
{
    /**
     * @param  array{client_evidence_id: string, task_id: string, description?: string|null, captured_at?: string|null}  $meta
     * @return array{evidence: LearnerEvidence, created: bool}
     */
    public function store(
        Learner $learner,
        Programme $programme,
        UploadedFile $file,
        array $meta,
        ?string $device,
    ): array {
        $clientId = $meta['client_evidence_id'];

        $existing = $this->find($learner, $clientId);

        if ($existing !== null) {
            return ['evidence' => $existing, 'created' => false];
        }

        $disk = (string) config('sync.evidence.disk', 'local');
        $directory = $this->directoryFor($learner, $meta['task_id']);
        $storedName = Str::uuid()->toString().'.'.$this->extensionFor($file);

        $checksum = hash_file('sha256', (string) $file->getRealPath());
        $byteSize = $file->getSize();
        $mimeType = $file->getMimeType() ?? 'application/octet-stream';

        $path = $file->storeAs($directory, $storedName, ['disk' => $disk]);

        try {
            $evidence = LearnerEvidence::create([
                'learner_id' => $learner->id,
                'programme_id' => $programme->id,
                'task_id' => $meta['task_id'],
                'client_evidence_id' => $clientId,
                'file_name' => $this->displayNameFor($file),
                'mime_type' => $mimeType,
                'byte_size' => $byteSize === false ? 0 : $byteSize,
                'checksum' => $checksum === false ? null : $checksum,
                'disk' => $disk,
                'storage_path' => $path,
                'description' => $meta['description'] ?? null,
                // Normalised to the app timezone for the same reason the
                // progress clocks are — see ProgressSynchroniser::time().
                'captured_at' => isset($meta['captured_at']) && $meta['captured_at'] !== null
                    ? Carbon::parse($meta['captured_at'])->setTimezone(config('app.timezone') ?: 'UTC')
                    : Carbon::now(),
                'received_at' => Carbon::now(),
                'last_device' => $device,
            ]);
        } catch (QueryException $e) {
            // Two retries of the same upload crossed in flight. The unique
            // index settled it; discard the copy that lost and hand back the
            // row that won, so the client still sees success.
            Storage::disk($disk)->delete($path);

            $winner = $this->find($learner, $clientId);

            if ($winner === null) {
                throw $e;
            }

            return ['evidence' => $winner, 'created' => false];
        }

        return ['evidence' => $evidence, 'created' => true];
    }

    public function find(Learner $learner, string $clientEvidenceId): ?LearnerEvidence
    {
        return LearnerEvidence::query()
            ->where('learner_id', $learner->id)
            ->where('client_evidence_id', $clientEvidenceId)
            ->first();
    }

    private function directoryFor(Learner $learner, string $taskId): string
    {
        // learner_ref is ours (NAL-2026-00001) and matches a fixed pattern;
        // the task id is the learner's device talking, so it is scrubbed to
        // a single harmless path segment.
        $task = preg_replace('/[^A-Za-z0-9._-]/', '-', $taskId) ?? 'task';
        $task = trim(substr($task, 0, 80), '.-');

        return 'evidence/'.$learner->learner_ref.'/'.($task === '' ? 'task' : $task);
    }

    private function extensionFor(UploadedFile $file): string
    {
        $allowed = (array) config('sync.evidence.extensions', []);
        $extension = strtolower((string) ($file->guessExtension() ?: $file->getClientOriginalExtension()));

        return in_array($extension, $allowed, true) ? $extension : 'bin';
    }

    /** What the learner and the assessor see. Never used as a path. */
    private function displayNameFor(UploadedFile $file): string
    {
        $name = basename((string) $file->getClientOriginalName());

        return $name === '' ? 'evidence' : Str::limit($name, 195, '');
    }
}
