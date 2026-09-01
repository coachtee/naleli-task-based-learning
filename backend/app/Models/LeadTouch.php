<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TouchChannel;
use App\Enums\TouchOutcome;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One thing somebody said to a lead, and what came of it.
 *
 * Kept as rows rather than a "last outcome" column because the sequence is
 * the information: three no-answers then a conversation is a live lead, three
 * no-answers and nothing else is one to stop paying attention to. A single
 * column cannot tell those apart.
 */
class LeadTouch extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'channel' => TouchChannel::class,
            'outcome' => TouchOutcome::class,
            'occurred_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /** Who made the call. Null once a staff member is deleted; the touch stays. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
