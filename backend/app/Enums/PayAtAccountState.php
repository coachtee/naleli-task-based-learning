<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Pay@'s own vocabulary for a request-to-pay, and the one place it is
 * translated into ours.
 *
 * Only three of these ten states mean the money is actually ours. Everything
 * else — including PAYMENT_FEES_ISSUE, where a payment exists but Pay@ has
 * flagged something about it — is held short of settlement so that a
 * registrar looks at it, rather than activating an enrolment on a state we
 * have never seen in production and cannot reason about.
 */
enum PayAtAccountState: string
{
    case PAYMENT_OUTSTANDING = 'PAYMENT_OUTSTANDING';
    case PROCESSING_PAYMENT = 'PROCESSING_PAYMENT';
    case PARTIAL_PAYMENT_RECEIVED = 'PARTIAL_PAYMENT_RECEIVED';
    case PAYMENT_FEES_ISSUE = 'PAYMENT_FEES_ISSUE';
    case PAYMENT_COMPLETED = 'PAYMENT_COMPLETED';
    case PAYMENT_READY_FOR_SETTLEMENT = 'PAYMENT_READY_FOR_SETTLEMENT';
    case SETTLEMENT_PROCESSED = 'SETTLEMENT_PROCESSED';
    case PAYMENT_CANCELLED = 'PAYMENT_CANCELLED';
    case PAYMENT_EXPIRED = 'PAYMENT_EXPIRED';
    case CANCELLED_DUE_TO_PRICING_PACKAGE_UPDATE = 'CANCELLED_DUE_TO_PRICING_PACKAGE_UPDATE';

    /** An unrecognised state is null rather than an exception: Pay@ may add one. */
    public static function tryFromApi(mixed $value): ?self
    {
        return is_string($value) ? self::tryFrom(strtoupper(trim($value))) : null;
    }

    public function label(): string
    {
        return match ($this) {
            self::PAYMENT_OUTSTANDING => 'Awaiting payment',
            self::PROCESSING_PAYMENT => 'Payment processing',
            self::PARTIAL_PAYMENT_RECEIVED => 'Part paid',
            self::PAYMENT_FEES_ISSUE => 'Paid — fee query at Pay@',
            self::PAYMENT_COMPLETED => 'Paid',
            self::PAYMENT_READY_FOR_SETTLEMENT => 'Paid — awaiting settlement',
            self::SETTLEMENT_PROCESSED => 'Paid and settled',
            self::PAYMENT_CANCELLED => 'Cancelled',
            self::PAYMENT_EXPIRED => 'Expired',
            self::CANCELLED_DUE_TO_PRICING_PACKAGE_UPDATE => 'Cancelled by Pay@',
        };
    }

    /** The money is ours. Nothing else may activate an enrolment. */
    public function isSettled(): bool
    {
        return match ($this) {
            self::PAYMENT_COMPLETED,
            self::PAYMENT_READY_FOR_SETTLEMENT,
            self::SETTLEMENT_PROCESSED => true,
            default => false,
        };
    }

    /** No further payment can arrive against this reference. */
    public function isClosed(): bool
    {
        return match ($this) {
            self::PAYMENT_CANCELLED,
            self::PAYMENT_EXPIRED,
            self::CANCELLED_DUE_TO_PRICING_PACKAGE_UPDATE => true,
            default => false,
        };
    }

    public function paymentStatus(): PaymentStatus
    {
        return match (true) {
            $this->isSettled() => PaymentStatus::SETTLED,
            $this->isClosed() => PaymentStatus::CANCELLED,
            default => PaymentStatus::PENDING,
        };
    }
}
