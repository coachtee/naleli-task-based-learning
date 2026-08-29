<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ActivationRule;
use App\Enums\BillingModel;
use App\Enums\OfferingStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The commercial package a programme is sold as.
 *
 * Everything the billing engine needs lives here, so "how much is Payroll and
 * how is it billed" is a row a registrar can read and change, not a decision
 * buried in whoever raised the invoices.
 */
class Offering extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'billing_model' => BillingModel::class,
            'activation_rule' => ActivationRule::class,
            'status' => OfferingStatus::class,
            'available_from' => 'date',
            'available_until' => 'date',
        ];
    }

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    public function enrolments(): HasMany
    {
        return $this->hasMany(Enrolment::class);
    }

    public function getPriceRandsAttribute(): string
    {
        return number_format($this->price_cents / 100, 2);
    }

    /** How this reads on a quote or an invoice line. */
    public function terms(): string
    {
        $price = 'R'.$this->price_rands;

        return match ($this->billing_model) {
            BillingModel::FIXED_BLOCK => $this->access_duration_days !== null
                ? "{$price} for {$this->accessMonths()}"
                : $price,
            BillingModel::DEPOSIT_BALANCE => sprintf(
                'R%s deposit, then R%s',
                number_format(($this->deposit_cents ?? 0) / 100, 2),
                number_format(($this->price_cents - ($this->deposit_cents ?? 0)) / 100, 2),
            ),
            BillingModel::SUBSCRIPTION => "{$price} per month",
            BillingModel::ONE_TIME => $price,
        };
    }

    public function accessMonths(): string
    {
        $days = $this->access_duration_days;

        if ($days === null) {
            return 'ongoing access';
        }

        $months = (int) round($days / 30);

        return $months === 1 ? '1 month' : "{$months} months";
    }
}
