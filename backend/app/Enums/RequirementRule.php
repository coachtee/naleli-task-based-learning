<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a learner qualifies for a programme.
 *
 * COMPLETED_PROGRAMME and CERTIFIED_IN_PROGRAMME are deliberately different.
 * Finishing the learning is not the same as being assessed competent, and it
 * is certification — a human decision, moderated where required — that opens a
 * Professional Specialisation. Gating on mere completion would let someone
 * click through tasks into a paid specialisation.
 */
enum RequirementRule: string
{
    case NONE = 'none';
    case COMPLETED_PROGRAMME = 'completed_programme';
    case CERTIFIED_IN_PROGRAMME = 'certified_in_programme';
    case RPL = 'rpl';
    case MANUAL_APPROVAL = 'manual_approval';

    public function label(): string
    {
        return match ($this) {
            self::NONE => 'None',
            self::COMPLETED_PROGRAMME => 'Completed programme',
            self::CERTIFIED_IN_PROGRAMME => 'Certified in programme',
            self::RPL => 'RPL',
            self::MANUAL_APPROVAL => 'Manual approval',
        };
    }
}
