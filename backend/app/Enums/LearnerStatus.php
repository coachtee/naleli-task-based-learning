<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a person sits in their relationship with the school. Independent of
 * any one programme — a learner is `active` while any enrolment is.
 */
enum LearnerStatus: string
{
    case PROSPECT = 'prospect';
    case APPLICANT = 'applicant';
    case ACTIVE = 'active';
    case ALUMNI = 'alumni';
    case WITHDRAWN = 'withdrawn';
    case SUSPENDED = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::PROSPECT => 'Prospect',
            self::APPLICANT => 'Applicant',
            self::ACTIVE => 'Active',
            self::ALUMNI => 'Alumni',
            self::WITHDRAWN => 'Withdrawn',
            self::SUSPENDED => 'Suspended',
        };
    }
}
