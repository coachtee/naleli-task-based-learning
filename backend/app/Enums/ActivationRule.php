<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What has to be paid before an enrolment activates.
 *
 * A deposit model usually activates on the deposit; an outright sale
 * activates on the full amount. Making it explicit per offering means the
 * commercial decision is configuration rather than a boolean somebody set on
 * one invoice by hand.
 */
enum ActivationRule: string
{
    case ON_FIRST_PAYMENT = 'on_first_payment';
    case ON_FULL_PAYMENT = 'on_full_payment';

    public function label(): string
    {
        return match ($this) {
            self::ON_FIRST_PAYMENT => 'On first payment',
            self::ON_FULL_PAYMENT => 'When fully paid',
        };
    }
}
