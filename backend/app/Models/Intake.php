<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\IntakeStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Intake extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => IntakeStatus::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
            'applications_open_on' => 'date',
            'applications_close_on' => 'date',
        ];
    }

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }
}
