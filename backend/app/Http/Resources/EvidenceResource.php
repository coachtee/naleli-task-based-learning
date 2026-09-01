<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A file the learner produced. `download_url` is how a device that has never
 * seen this file gets it — a learner who worked at home and then sat down at
 * a lab PC fetches her own evidence back the same way she sent it.
 */
class EvidenceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'client_evidence_id' => $this->client_evidence_id,
            'task_id' => $this->task_id,
            'file_name' => $this->file_name,
            'mime_type' => $this->mime_type,
            'byte_size' => $this->byte_size,
            'checksum' => $this->checksum,
            'description' => $this->description,
            'captured_at' => $this->captured_at?->toIso8601String(),
            'received_at' => $this->received_at?->toIso8601String(),
            'download_url' => route('api.v1.me.evidence.show', ['evidence' => $this->client_evidence_id]),
        ];
    }
}
