<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProgrammeStatus;
use App\Enums\ProgrammeTier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Programme extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tier' => ProgrammeTier::class,
            'status' => ProgrammeStatus::class,
        ];
    }

    public function intakes(): HasMany
    {
        return $this->hasMany(Intake::class);
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(ProgrammeRequirement::class);
    }

    public function enrolments(): HasMany
    {
        return $this->hasMany(Enrolment::class);
    }
}
