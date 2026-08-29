<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How an offering is sold. This is the decision that was previously implicit
 * in whatever invoices a registrar happened to raise, which is how a fixed
 * three-month block came to be billed as three monthly instalments.
 *
 * The billing model now lives on the offering, and the invoices are derived
 * from it — so a block is one invoice because the model says so, not because
 * someone remembered.
 */
enum BillingModel: string
{
    /** Pay once, get access. A short course, or a programme sold outright. */
    case ONE_TIME = 'one_time';

    /** A deposit that activates access, then a balance owed by a due date. */
    case DEPOSIT_BALANCE = 'deposit_balance';

    /**
     * A fixed-duration block: one price, one payment, access for a set number
     * of days. R950 for three months is this — one invoice, not three.
     */
    case FIXED_BLOCK = 'fixed_block';

    /** Recurring billing. Modelled but not enabled; see FeeSchedule. */
    case SUBSCRIPTION = 'subscription';

    public function label(): string
    {
        return match ($this) {
            self::ONE_TIME => 'One-time',
            self::DEPOSIT_BALANCE => 'Deposit + balance',
            self::FIXED_BLOCK => 'Block',
            self::SUBSCRIPTION => 'Monthly',
        };
    }

    /** Whether invoice generation is implemented for this model yet. */
    public function isEnabled(): bool
    {
        return $this !== self::SUBSCRIPTION;
    }
}
