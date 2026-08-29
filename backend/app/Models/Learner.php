<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\IdType;
use App\Enums\LearnerStatus;
use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;

/**
 * A person, once. The learner reference is allocated exactly once by
 * LearnerRegistry and nothing else in the system may write it — which is what
 * makes "one learner, one ID for life" an enforceable property rather than a
 * convention.
 */
class Learner extends Model implements AuthenticatableContract
{
    // Learners authenticate by device token, never by password — there is no
    // learner login and no learner row in `users`. The Authenticatable trait
    // is here only so the sanctum guard will accept a Learner as the
    // authenticated party; its password methods are never called.
    use AuthenticatableTrait;
    use HasApiTokens;
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'id_type' => IdType::class,
            'status' => LearnerStatus::class,
            'id_number_encrypted' => 'encrypted',
            'date_of_birth' => 'date',
            'identity_verified_at' => 'datetime',
            'profile_completed_at' => 'datetime',
        ];
    }

    public function identifiers(): HasMany
    {
        return $this->hasMany(LearnerIdentifier::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function enrolments(): HasMany
    {
        return $this->hasMany(Enrolment::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function entitlements(): HasMany
    {
        return $this->hasMany(Entitlement::class);
    }

    public function accessTokens(): HasMany
    {
        return $this->hasMany(AccessToken::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /** The gate AccessTokenIssuer refuses to pass without. */
    public function hasVerifiedIdentity(): bool
    {
        return $this->identity_verified_at !== null;
    }
}
