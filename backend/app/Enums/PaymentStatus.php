<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Money's own lifecycle, separate from the invoice it settles. Only the first
 * transition to `settled` activates anything.
 */
enum PaymentStatus: string
{
    case INITIATED = 'initiated';
    case PENDING = 'pending';
    case SETTLED = 'settled';
    case FAILED = 'failed';
    case REFUNDED = 'refunded';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::INITIATED => 'Initiated',
            self::PENDING => 'Pending',
            self::SETTLED => 'Settled',
            self::FAILED => 'Failed',
            self::REFUNDED => 'Refunded',
            self::CANCELLED => 'Cancelled',
        };
    }
}
