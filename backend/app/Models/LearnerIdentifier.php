<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\IdentifierType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Every match key that has ever pointed at a learner, kept after they change it. */
class LearnerIdentifier extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['type' => IdentifierType::class];
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }
}
