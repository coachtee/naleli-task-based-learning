<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RequirementRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgrammeRequirement extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'rule_type' => RequirementRule::class,
            'requires_certificate' => 'boolean',
        ];
    }

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    public function requiredProgramme(): BelongsTo
    {
        return $this->belongsTo(Programme::class, 'requires_programme_id');
    }
}
