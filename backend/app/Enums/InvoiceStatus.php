<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * One amount owed against an enrolment.
 */
enum InvoiceStatus: string
{
    case DUE = 'due';
    case PAID = 'paid';
    case VOID = 'void';
    case REFUNDED = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::DUE => 'Due',
            self::PAID => 'Paid',
            self::VOID => 'Void',
            self::REFUNDED => 'Refunded',
        };
    }
}
