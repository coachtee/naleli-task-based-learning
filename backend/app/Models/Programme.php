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

    public function offerings(): HasMany
    {
        return $this->hasMany(Offering::class);
    }

    /**
     * The programmes this one opens up.
     *
     * Deliberately the reverse of `requirements` rather than a second column:
     * "Payroll requires certification in Digital Office Administration" and
     * "Digital Office Administration unlocks Payroll" are one fact, and
     * storing it twice is how the two come to disagree.
     */
    public function unlocks(): HasMany
    {
        return $this->hasMany(ProgrammeRequirement::class, 'requires_programme_id');
    }
}
