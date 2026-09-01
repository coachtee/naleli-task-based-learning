<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A file the learner produced. Written answers arrive here too, as text/plain
 * — the app deliberately keeps one evidence path rather than a second code
 * path for typed work, and the backend follows it.
 */
class LearnerEvidence extends Model
{
    protected $table = 'learner_evidence';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'byte_size' => 'integer',
            'captured_at' => 'datetime',
            'received_at' => 'datetime',
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
