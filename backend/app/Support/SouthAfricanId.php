<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;

/**
 * A South African ID number, validated.
 *
 * Validating at the point of capture is what stops a mistyped digit becoming a
 * second learner record for a person who already exists — and the embedded
 * date of birth cross-checks the one on the application form for free.
 *
 * Format: YYMMDD SSSS C A Z
 *   0-5   date of birth
 *   6-9   sequence (encodes gender; deliberately not read here)
 *   10    citizenship, 0 = citizen, 1 = permanent resident
 *   11    historically race, now always 8; not checked
 *   12    Luhn check digit over the preceding twelve
 */
final class SouthAfricanId
{
    private function __construct(
        public readonly string $number,
        public readonly ?DateTimeImmutable $dateOfBirth,
        public readonly bool $permanentResident,
    ) {}

    public static function isValid(string $raw): bool
    {
        return self::parse($raw) !== null;
    }

    public static function parse(string $raw): ?self
    {
        $digits = preg_replace('/\D/', '', $raw) ?? '';

        if (strlen($digits) !== 13) {
            return null;
        }

        if (! self::luhnPasses($digits)) {
            return null;
        }

        $dob = self::extractDateOfBirth($digits);

        if ($dob === null) {
            return null;
        }

        return new self($digits, $dob, $digits[10] === '1');
    }

    /**
     * Standard Luhn over all thirteen digits: the total must be divisible by
     * ten, which is only true when the final check digit is correct.
     */
    private static function luhnPasses(string $digits): bool
    {
        $sum = 0;
        $double = false;

        for ($i = 12; $i >= 0; $i--) {
            $value = (int) $digits[$i];

            if ($double) {
                $value *= 2;
                if ($value > 9) {
                    $value -= 9;
                }
            }

            $sum += $value;
            $double = ! $double;
        }

        return $sum % 10 === 0;
    }

    /**
     * The century is not encoded, so a two-digit year later than the current
     * one must belong to the previous century — the usual convention, and the
     * reason a date extracted here is a cross-check rather than the source of
     * truth for age.
     */
    private static function extractDateOfBirth(string $digits): ?DateTimeImmutable
    {
        $year = (int) substr($digits, 0, 2);
        $month = (int) substr($digits, 2, 2);
        $day = (int) substr($digits, 4, 2);

        $currentTwoDigitYear = (int) date('y');
        $century = $year > $currentTwoDigitYear ? 1900 : 2000;
        $fullYear = $century + $year;

        if (! checkdate($month, $day, $fullYear)) {
            return null;
        }

        return DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            sprintf('%04d-%02d-%02d 00:00:00', $fullYear, $month, $day),
        ) ?: null;
    }
}
