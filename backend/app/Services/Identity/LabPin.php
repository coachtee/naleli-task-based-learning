<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Models\Learner;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

/**
 * The PIN a learner types at a lab PC.
 *
 * Hashed, never stored or logged in the clear, and never returned by any API —
 * the facilitator who sets one reads it off the screen once and tells the
 * learner. There is no "remind me what my PIN is", only "set a new one",
 * because the first is a way to hand somebody else's PIN to whoever is
 * standing at the counter.
 */
class LabPin
{
    public const LENGTH = 6;

    /** @return string the PIN, to be read out once and not stored anywhere else */
    public function issue(Learner $learner): string
    {
        $pin = str_pad((string) random_int(0, 10 ** self::LENGTH - 1), self::LENGTH, '0', STR_PAD_LEFT);

        $this->set($learner, $pin);

        return $pin;
    }

    public function set(Learner $learner, string $pin): void
    {
        if (! $this->looksLikeAPin($pin)) {
            throw new InvalidArgumentException('A lab PIN is '.self::LENGTH.' digits.');
        }

        $learner->forceFill([
            'pin_hash' => Hash::make($pin),
            'pin_set_at' => Carbon::now(),
        ])->save();
    }

    public function verify(Learner $learner, string $pin): bool
    {
        if ($learner->pin_hash === null) {
            // Still spend the time a real check would, so "no PIN set" and
            // "wrong PIN" cannot be told apart by how long the answer takes.
            Hash::check($pin, '$2y$12$'.str_repeat('0', 53));

            return false;
        }

        return Hash::check($pin, $learner->pin_hash);
    }

    public function clear(Learner $learner): void
    {
        $learner->forceFill(['pin_hash' => null, 'pin_set_at' => null])->save();
    }

    private function looksLikeAPin(string $pin): bool
    {
        return preg_match('/^\d{'.self::LENGTH.'}$/', $pin) === 1;
    }
}
