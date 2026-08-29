<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EntitlementState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What a learner may open right now, denormalised. When the Android app
 * arrives in Phase 3 this is the only table it reads to decide what to show.
 */
class Entitlement extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'state' => EntitlementState::class,
            'unlocked_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    public function sourceEnrolment(): BelongsTo
    {
        return $this->belongsTo(Enrolment::class, 'source_enrolment_id');
    }
}
