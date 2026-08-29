<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How this registration is being paid for, asked once at registration as a
 * single question rather than as a second form.
 *
 * Choosing "applying for funding" must never interrupt the registration. It
 * records an intention and nothing more; the funding detail and its documents
 * are collected later, against the same person record.
 */
enum FundingSource: string
{
    case SELF = 'self';
    case EMPLOYER = 'employer';
    case FUNDING_APPLICATION = 'funding_application';
    case BURSARY = 'bursary';
    case OTHER_SPONSOR = 'other_sponsor';

    public function label(): string
    {
        return match ($this) {
            self::SELF => 'I am paying for myself',
            self::EMPLOYER => 'Employer sponsored',
            self::FUNDING_APPLICATION => 'Applying for funding',
            self::BURSARY => 'Bursary or scholarship',
            self::OTHER_SPONSOR => 'Another sponsor',
        };
    }

    /** Shorter, for a table cell. */
    public function shortLabel(): string
    {
        return match ($this) {
            self::SELF => 'Self-funded',
            self::EMPLOYER => 'Employer',
            self::FUNDING_APPLICATION => 'Funding applied for',
            self::BURSARY => 'Bursary',
            self::OTHER_SPONSOR => 'Sponsor',
        };
    }

    /** Anything but self-funding leaves a funding matter open. */
    public function needsFundingWork(): bool
    {
        return $this !== self::SELF;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }
}
