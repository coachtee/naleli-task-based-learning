<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The three tiers the KCS catalogue is organised into, plus short courses.
 */
enum ProgrammeTier: string
{
    case CAREER_MODULE = 'career_module';
    case PROFESSIONAL = 'professional';
    case FOUNDATION = 'foundation';
    case SHORT_COURSE = 'short_course';

    public function label(): string
    {
        return match ($this) {
            self::CAREER_MODULE => 'Career module',
            self::PROFESSIONAL => 'Professional',
            self::FOUNDATION => 'Foundation',
            self::SHORT_COURSE => 'Short course',
        };
    }
}
