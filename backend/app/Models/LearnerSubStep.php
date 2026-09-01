<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One step of one task, done. The smallest unit of progress the app records,
 * and the thing two devices most often both have an opinion about.
 */
class LearnerSubStep extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'complete' => 'boolean',
            'completed_at' => 'datetime',
            'client_updated_at' => 'datetime',
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
}
