<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One append-only log for everything that arrives from outside — the Fluent
 * Forms application webhook and, from Phase 2, payment gateway callbacks.
 * Every delivery is recorded before it is interpreted, so a provider dispute
 * or a lost application is answerable from our own records.
 */
class InboundWebhook extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'signature_valid' => 'boolean',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function related(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'related_type', 'related_id');
    }
}
