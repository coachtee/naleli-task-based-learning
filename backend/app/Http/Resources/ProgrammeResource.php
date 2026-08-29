<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProgrammeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'slug' => $this->slug,
            'tier' => $this->tier->value,
            'summary' => $this->summary,
            'duration_label' => $this->duration_label,
            'duration_days' => $this->duration_days,
            'weekly_hours' => $this->weekly_hours,
            'fee_note' => $this->fee_note,
            'content_code' => $this->content_code,
            'content_version' => $this->content_version,
        ];
    }
}
