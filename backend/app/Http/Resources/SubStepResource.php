<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** One step of the work, as the school holds it. */
class SubStepResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'sub_step_id' => $this->sub_step_id,
            'task_id' => $this->task_id,
            'complete' => (bool) $this->complete,
            'completed_at' => $this->completed_at?->toIso8601String(),
        ];
    }
}
