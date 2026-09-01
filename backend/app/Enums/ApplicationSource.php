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
    case META_LEAD = 'meta_lead';
    case ADMIN = 'admin';
    case WALK_IN = 'walk_in';

    public function label(): string
    {
        return match ($this) {
            self::FLUENTFORM => 'Website form',
            self::META_LEAD => 'Facebook or Instagram lead',
            self::ADMIN => 'Captured by staff',
            self::WALK_IN => 'Walk-in',
        };
    }
}
