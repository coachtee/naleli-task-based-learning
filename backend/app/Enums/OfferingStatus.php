<?php

declare(strict_types=1);

namespace App\Enums;

enum OfferingStatus: string
{
    case DRAFT = 'draft';
    case OPEN = 'open';
    case CLOSED = 'closed';
    case ARCHIVED = 'archived';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
