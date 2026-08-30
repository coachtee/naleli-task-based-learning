<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How kcs.edu.za organises what it sells.
 *
 * The tiers are taken from the live Student Application Form's own
 * "Choose Your Enrolment Plan" categories, because that is the form real
 * applicants complete and therefore the only list that has ever produced a
 * paying student. Career modules and the professional tier come from the site
 * navigation instead, and are kept apart for exactly that reason.
 */
enum ProgrammeTier: string
{
    case CAREER_MODULE = 'career_module';
    case PROFESSIONAL = 'professional';
    case FOUNDATION = 'foundation';
    case SHORT_COURSE = 'short_course';
    case QCTO = 'qcto';
    case MODULAR_SKILL = 'modular_skill';

    public function label(): string
    {
        return match ($this) {
            self::CAREER_MODULE => 'Career module',
            self::PROFESSIONAL => 'Professional',
            self::FOUNDATION => 'Foundation',
            self::SHORT_COURSE => 'KCS short course',
            self::QCTO => 'NIBS QCTO programme',
            self::MODULAR_SKILL => 'Modular skills course',
        };
    }
}
