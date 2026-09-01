<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What came of it.
 *
 * Deliberately includes the unglamorous ones. "No answer" recorded three times
 * is the difference between a lead worth chasing and one worth closing, and a
 * system that only records successes cannot tell them apart.
 */
enum TouchOutcome: string
{
    case NO_ANSWER = 'no_answer';
    case WRONG_NUMBER = 'wrong_number';
    case LEFT_MESSAGE = 'left_message';
    case SPOKE = 'spoke';
    case SENT_INFO = 'sent_info';
    case WILL_REGISTER = 'will_register';
    case NOT_NOW = 'not_now';
    case NOT_INTERESTED = 'not_interested';

    public function label(): string
    {
        return match ($this) {
            self::NO_ANSWER => 'No answer',
            self::WRONG_NUMBER => 'Wrong number',
            self::LEFT_MESSAGE => 'Left a message',
            self::SPOKE => 'Spoke to them',
            self::SENT_INFO => 'Sent the course info',
            self::WILL_REGISTER => 'Says they will register',
            self::NOT_NOW => 'Interested, not this intake',
            self::NOT_INTERESTED => 'Not interested',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::WILL_REGISTER => 'success',
            self::SPOKE, self::SENT_INFO => 'info',
            self::NOT_NOW => 'warning',
            self::NOT_INTERESTED, self::WRONG_NUMBER => 'danger',
            default => 'gray',
        };
    }

    /**
     * When to come back, in days. Null means the lead is closed and should
     * leave the queue rather than sit in it being skipped every morning.
     */
    public function nextActionInDays(): ?int
    {
        return match ($this) {
            self::NO_ANSWER, self::LEFT_MESSAGE => 2,
            self::SPOKE, self::SENT_INFO => 3,
            self::WILL_REGISTER => 2,
            self::NOT_NOW => 42,
            self::NOT_INTERESTED, self::WRONG_NUMBER => null,
        };
    }

    public function closesTheLead(): bool
    {
        return $this === self::NOT_INTERESTED || $this === self::WRONG_NUMBER;
    }
}
