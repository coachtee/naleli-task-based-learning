<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Staff roles for Phase 1. Assessor and moderator arrive in Phase 4, which is
 * the right moment to bring in a permissions package rather than now.
 */
enum UserRole: string
{
    case ADMIN = 'admin';
    case REGISTRAR = 'registrar';
    case FINANCE = 'finance';

    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'Admin',
            self::REGISTRAR => 'Registrar',
            self::FINANCE => 'Finance',
        };
    }
}
