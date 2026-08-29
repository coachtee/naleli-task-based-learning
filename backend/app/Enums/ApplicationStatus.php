<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The admissions pipeline. `awaiting_identity` exists because identification
 * is optional at application and required before a token is issued.
 */
enum ApplicationStatus: string
{
    case APPLIED = 'applied';
    case AWAITING_IDENTITY = 'awaiting_identity';
    case AWAITING_PAYMENT = 'awaiting_payment';
    case PAID = 'paid';
    case ENROLLED = 'enrolled';
    case WITHDRAWN = 'withdrawn';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::APPLIED => 'Applied',
            self::AWAITING_IDENTITY => 'Awaiting identity',
            self::AWAITING_PAYMENT => 'Awaiting payment',
            self::PAID => 'Paid',
            self::ENROLLED => 'Enrolled',
            self::WITHDRAWN => 'Withdrawn',
            self::REJECTED => 'Rejected',
        };
    }
}
