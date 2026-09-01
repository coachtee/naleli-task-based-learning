<?php

declare(strict_types=1);

namespace App\Enums;

/** How somebody was reached. */
enum TouchChannel: string
{
    case PHONE = 'phone';
    case WHATSAPP = 'whatsapp';
    case EMAIL = 'email';
    case SMS = 'sms';
    case IN_PERSON = 'in_person';

    public function label(): string
    {
        return match ($this) {
            self::PHONE => 'Phone call',
            self::WHATSAPP => 'WhatsApp',
            self::EMAIL => 'Email',
            self::SMS => 'SMS',
            self::IN_PERSON => 'In person',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::PHONE => 'heroicon-o-phone',
            self::WHATSAPP => 'heroicon-o-chat-bubble-left-right',
            self::EMAIL => 'heroicon-o-envelope',
            self::SMS => 'heroicon-o-device-phone-mobile',
            self::IN_PERSON => 'heroicon-o-user',
        };
    }
}
