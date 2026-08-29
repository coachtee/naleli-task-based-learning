<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Match keys are only useful if the same value always produces the same
 * string. Two applications from "Thabo@Gmail.com " and "thabo@gmail.com" are
 * one person, and "082 123 4567" and "+27821234567" are one phone.
 */
final class Normalise
{
    public static function email(?string $email): ?string
    {
        $email = trim((string) $email);

        return $email === '' ? null : mb_strtolower($email);
    }

    /**
     * South African numbers to E.164. A local 0-prefixed number becomes +27,
     * anything already international is kept, and anything unrecognisable is
     * returned digits-only rather than discarded — a bad number is still a
     * usable match key.
     */
    public static function phone(?string $phone): ?string
    {
        $raw = trim((string) $phone);

        if ($raw === '') {
            return null;
        }

        $plus = str_starts_with($raw, '+');
        $digits = preg_replace('/\D/', '', $raw) ?? '';

        if ($digits === '') {
            return null;
        }

        if ($plus) {
            return '+'.$digits;
        }

        if (str_starts_with($digits, '27') && strlen($digits) === 11) {
            return '+'.$digits;
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            return '+27'.substr($digits, 1);
        }

        return $digits;
    }

    public static function idNumber(?string $number): ?string
    {
        $number = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $number) ?? '');

        return $number === '' ? null : $number;
    }

    /** The match key: hashed, so identity matching never needs the plaintext. */
    public static function idHash(string $normalisedNumber): string
    {
        return hash('sha256', $normalisedNumber);
    }

    /** What appears in dashboard lists: enough to recognise, not enough to use. */
    public static function maskId(string $normalisedNumber): string
    {
        $length = strlen($normalisedNumber);

        if ($length <= 6) {
            return str_repeat('•', $length);
        }

        return substr($normalisedNumber, 0, 4)
            .str_repeat('•', $length - 7)
            .substr($normalisedNumber, -3);
    }
}
