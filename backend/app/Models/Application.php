<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ApplicationSource;
use App\Enums\ApplicationStatus;
use App\Enums\FundingSource;
use App\Enums\FundingStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Application extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'source' => ApplicationSource::class,
            'funding_source' => FundingSource::class,
            'funding_status' => FundingStatus::class,
            'payload' => 'array',
            'applied_at' => 'datetime',
            'first_contacted_at' => 'datetime',
            'decided_at' => 'datetime',
            'registered_at' => 'datetime',
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

    /**
     * A funding matter that still needs someone's attention. Self-funded
     * registrations never raise one; everything else does until it is settled.
     */
    public function hasOpenFundingMatter(): bool
    {
        return $this->funding_source?->needsFundingWork() === true
            && ($this->funding_status?->isOpen() ?? true);
    }
}
