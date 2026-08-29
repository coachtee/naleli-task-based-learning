<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Whether a programme may currently be applied for.
 */
enum ProgrammeStatus: string
{
    case DRAFT = 'draft';
    case OPEN = 'open';
    case CLOSED = 'closed';
    case ARCHIVED = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::OPEN => 'Open',
            self::CLOSED => 'Closed',
            self::ARCHIVED => 'Archived',
        };
    }
}
