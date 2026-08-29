<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a learner may open right now. This is the only thing the Android app
 * reads to decide what to show.
 */
enum EntitlementState: string
{
    case LOCKED = 'locked';
    case AVAILABLE = 'available';
    case ACTIVE = 'active';
    case COMPLETED = 'completed';
    case EXPIRED = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::LOCKED => 'Locked',
            self::AVAILABLE => 'Available',
            self::ACTIVE => 'Active',
            self::COMPLETED => 'Completed',
            self::EXPIRED => 'Expired',
        };
    }
}
