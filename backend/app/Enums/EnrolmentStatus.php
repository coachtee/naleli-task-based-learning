<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A learner actually doing a programme. `pending` until the activating
 * invoice settles.
 */
enum EnrolmentStatus: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case COMPLETED = 'completed';
    case WITHDRAWN = 'withdrawn';
    case EXPIRED = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::ACTIVE => 'Active',
            self::SUSPENDED => 'Suspended',
            self::COMPLETED => 'Completed',
            self::WITHDRAWN => 'Withdrawn',
            self::EXPIRED => 'Expired',
        };
    }
}
