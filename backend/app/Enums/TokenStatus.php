<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * `issued` means printed or emailed but never redeemed; `active` means a
 * device has claimed it.
 */
enum TokenStatus: string
{
    case ISSUED = 'issued';
    case ACTIVE = 'active';
    case REVOKED = 'revoked';
    case EXPIRED = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::ISSUED => 'Issued',
            self::ACTIVE => 'Active',
            self::REVOKED => 'Revoked',
            self::EXPIRED => 'Expired',
        };
    }
}
