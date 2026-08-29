<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The learner as the app is allowed to see them.
 *
 * Deliberately narrow: no ID number, no masked ID, no internal database id,
 * no notes. The app needs to greet the learner and show their reference —
 * everything else is the school's record, not the phone's.
 */
class LearnerResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'learner_ref' => $this->learner_ref,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'preferred_name' => $this->preferred_name,
            'email' => $this->email,
            'status' => $this->status->value,
            'first_registered_year' => $this->first_registered_year,
        ];
    }
}
