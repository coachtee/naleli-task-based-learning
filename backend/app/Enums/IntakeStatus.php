<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The lifecycle of one cohort, e.g. February 2027.
 */
enum IntakeStatus: string
{
    case PLANNED = 'planned';
    case OPEN = 'open';
    case CLOSED = 'closed';
    case RUNNING = 'running';
    case COMPLETED = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::PLANNED => 'Planned',
            self::OPEN => 'Open',
            self::CLOSED => 'Closed',
            self::RUNNING => 'Running',
            self::COMPLETED => 'Completed',
        };
    }
}
