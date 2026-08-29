<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A match key that has ever pointed at a learner. ID numbers are deliberately
 * absent — learners.id_number_hash already carries that uniqueness.
 */
enum IdentifierType: string
{
    case EMAIL = 'email';
    case PHONE = 'phone';
    case LEGACY_CODE = 'legacy_code';

    public function label(): string
    {
        return match ($this) {
            self::EMAIL => 'Email',
            self::PHONE => 'Phone',
            self::LEGACY_CODE => 'Legacy code',
        };
    }
}
