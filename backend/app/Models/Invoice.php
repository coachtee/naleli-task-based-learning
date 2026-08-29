<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One amount owed against an enrolment. Deliberately a ledger and not a
 * billing engine: there is no recurrence, no plan and no cycle here, because
 * every commercial model under consideration is expressible as rows in this
 * table and the rules that generate them are the only part that depends on a
 * decision still open.
 */
class Invoice extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'activates_enrolment' => 'boolean',
            'due_on' => 'date',
            'paid_at' => 'datetime',
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

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function getAmountRandsAttribute(): string
    {
        return number_format($this->amount_cents / 100, 2);
    }
}
