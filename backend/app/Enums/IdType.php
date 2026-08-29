<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The kind of identification on file. Recorded alongside the number because
 * validation rules differ: only an SA ID has a check digit and an embedded
 * date of birth.
 */
enum IdType: string
{
    case SA_ID = 'sa_id';
    case PASSPORT = 'passport';
    case ASYLUM_PERMIT = 'asylum_permit';

    public function label(): string
    {
        return match ($this) {
            self::SA_ID => 'SA ID',
            self::PASSPORT => 'Passport',
            self::ASYLUM_PERMIT => 'Asylum permit',
        };
    }
}
