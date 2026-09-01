<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A hand-in and its outcome.
 *
 * The client sent `submitted_at` and `confidence_rating`; it is being told
 * `result`, `assessed_at` and `feedback`. That direction never reverses —
 * there is no route that accepts them.
 */
class SubmissionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'task_id' => $this->task_id,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'confidence_rating' => $this->confidence_rating,
            'result' => $this->result->value,
            'assessed_at' => $this->assessed_at?->toIso8601String(),
            'feedback' => $this->feedback,
        ];
    }
}
