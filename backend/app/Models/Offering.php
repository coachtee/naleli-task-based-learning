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
            // Written the way it is said out loud: R500 registration, then
            // R950 a month for three months. A registrar reading a list needs
            // to recognise the deal, not reconstruct it from a total.
            BillingModel::DEPOSIT_BALANCE => $this->depositTerms(),
            BillingModel::SUBSCRIPTION => "{$price} per month",
            BillingModel::ONE_TIME => $price,
        };
    }

    private function depositTerms(): string
    {
        $deposit = $this->deposit_cents ?? 0;
        $balance = $this->price_cents - $deposit;
        $count = max(1, $this->instalment_count ?? 1);
        $per = intdiv($balance, $count);

        $registration = 'R'.number_format($deposit / 100, 2).' registration';

        if ($count === 1) {
            return $registration.', then R'.number_format($balance / 100, 2);
        }

        return sprintf(
            '%s, then R%s x %d months',
            $registration,
            number_format($per / 100, 2),
            $count,
        );
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
