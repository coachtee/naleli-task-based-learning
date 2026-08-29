<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a learner qualifies for a programme. Seeded in Phase 1, enforced in
 * Phase 5 when completion exists to test against.
 */
enum RequirementRule: string
{
    case NONE = 'none';
    case COMPLETED_PROGRAMME = 'completed_programme';
    case RPL = 'rpl';
    case MANUAL_APPROVAL = 'manual_approval';

    public function label(): string
    {
        return match ($this) {
            self::NONE => 'None',
            self::COMPLETED_PROGRAMME => 'Completed programme',
            self::RPL => 'RPL',
            self::MANUAL_APPROVAL => 'Manual approval',
        };
    }
}
