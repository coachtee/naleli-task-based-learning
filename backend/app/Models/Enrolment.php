<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EnrolmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Enrolment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => EnrolmentStatus::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
            'activated_at' => 'datetime',
            'completed_at' => 'datetime',
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

    public function intake(): BelongsTo
    {
        return $this->belongsTo(Intake::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /** The commercial terms this enrolment was sold under. */
    public function offering(): BelongsTo
    {
        return $this->belongsTo(Offering::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function accessTokens(): HasMany
    {
        return $this->hasMany(AccessToken::class);
    }

    /** The invoice whose settlement turns this enrolment on. */
    public function activatingInvoice(): ?Invoice
    {
        return $this->invoices()->where('activates_enrolment', true)->first();
    }
}
