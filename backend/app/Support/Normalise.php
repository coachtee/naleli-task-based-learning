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

    /**
     * The digits wa.me wants: international, no plus, no spaces.
     *
     * Returns null for anything that is not a plausible South African mobile,
     * because a WhatsApp link built on a landline or a typo opens a chat with
     * a stranger — and the message carries a learner's name and what they owe.
     */
    public static function whatsappNumber(?string $phone): ?string
    {
        $e164 = self::phone($phone);

        if ($e164 === null) {
            return null;
        }

        $digits = ltrim($e164, '+');

        // 27 followed by a mobile prefix (6, 7 or 8) and eight more digits.
        return preg_match('/^27[6-8]\d{8}$/', $digits) === 1 ? $digits : null;
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
