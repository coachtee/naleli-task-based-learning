<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where an application came from. `fluentform` is the live website form 8;
 * the other two are captured by staff in the dashboard.
 */
enum ApplicationSource: string
{
    case FLUENTFORM = 'fluentform';
    case ADMIN = 'admin';
    case WALK_IN = 'walk_in';

    public function label(): string
    {
        return match ($this) {
            self::FLUENTFORM => 'Fluentform',
            self::ADMIN => 'Admin',
            self::WALK_IN => 'Walk in',
        };
    }
}
