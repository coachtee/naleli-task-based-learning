<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CompetenceResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A task handed in, and what it counted for.
 *
 * `submitted_at` and `confidence_rating` come from the learner's device.
 * `result`, `assessed_at`, `assessed_by` and `feedback` never do — no sync
 * path writes them, which is the whole reason they live on this row rather
 * than being inferred from progress.
 */
class LearnerSubmission extends Model
{
    protected $guarded = ['id'];

    /** The columns a device is allowed to move. Anything not listed here is
     * the school's to write; ProgressSynchroniser upserts only these. */
    public const DEVICE_WRITABLE = [
        'submitted_at',
        'confidence_rating',
        'client_updated_at',
        'last_device',
    ];

    protected function casts(): array
    {
        return [
            'result' => CompetenceResult::class,
            'submitted_at' => 'datetime',
            'assessed_at' => 'datetime',
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

    public function assessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessed_by');
    }
}
