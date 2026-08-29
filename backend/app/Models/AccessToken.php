<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TokenStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Programme access, not learner identity. A learner has one permanent
 * reference and as many tokens as they have enrolments — which is what lets
 * the same person open a second programme in the same app without
 * registering again.
 */
class AccessToken extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => TokenStatus::class,
            'issued_at' => 'datetime',
            'activated_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }

    public function enrolment(): BelongsTo
    {
        return $this->belongsTo(Enrolment::class);
    }

    public function isRedeemable(): bool
    {
        return $this->status === TokenStatus::ISSUED
            && $this->revoked_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
