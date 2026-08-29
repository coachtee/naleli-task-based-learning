<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a funding matter stands. Kept separate from the registration's own
 * status so that a learner can be paid up and studying while a bursary claim
 * is still in progress behind them — the common case, and one a single status
 * column cannot express.
 */
enum FundingStatus: string
{
    case NOT_REQUIRED = 'not_required';
    case PENDING = 'pending';
    case SUBMITTED = 'submitted';
    case APPROVED = 'approved';
    case DECLINED = 'declined';

    public function label(): string
    {
        return match ($this) {
            self::NOT_REQUIRED => 'Not required',
            self::PENDING => 'Pending',
            self::SUBMITTED => 'Submitted',
            self::APPROVED => 'Approved',
            self::DECLINED => 'Declined',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::PENDING, self::SUBMITTED], true);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }
}
