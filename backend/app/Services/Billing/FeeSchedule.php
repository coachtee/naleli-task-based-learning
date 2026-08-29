<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\ActivationRule;
use App\Enums\BillingModel;
use App\Models\Offering;
use DomainException;

/**
 * Turns an offering into the invoices it owes.
 *
 * This class exists because the alternative was letting whoever accepted an
 * application decide the invoice shape by hand — which is exactly how a fixed
 * three-month block came to be billed as three monthly instalments. The
 * commercial model is configuration on the offering; the invoice rows are
 * derived from it here and nowhere else.
 *
 * A fixed block is ONE invoice. The duration describes how long access lasts,
 * not how many times the learner pays.
 */
class FeeSchedule
{
    /**
     * @return array<int, FeeLine>
     */
    public function linesFor(Offering $offering): array
    {
        if (! $offering->billing_model->isEnabled()) {
            throw new DomainException(
                "Billing model [{$offering->billing_model->value}] is modelled but not enabled. ".
                'Enable it deliberately when the commercial model calls for it.',
            );
        }

        return match ($offering->billing_model) {
            BillingModel::ONE_TIME, BillingModel::FIXED_BLOCK => $this->single($offering),
            BillingModel::DEPOSIT_BALANCE => $this->depositAndBalance($offering),
            BillingModel::SUBSCRIPTION => [],
        };
    }

    /**
     * One price, one invoice.
     *
     * R950 for three months is a single charge for ninety days of access. The
     * three months are the access_duration_days on the offering; they are not
     * three payments and must never be rendered as three invoices.
     *
     * @return array<int, FeeLine>
     */
    private function single(Offering $offering): array
    {
        $description = $offering->billing_model === BillingModel::FIXED_BLOCK
            ? "{$offering->name} — {$offering->accessMonths()} access"
            : $offering->name;

        return [
            new FeeLine(
                sequence: 1,
                description: $description,
                amountCents: $offering->price_cents,
                activatesEnrolment: true,
                dueInDays: 0,
            ),
        ];
    }

    /**
     * A deposit that opens access, then the balance.
     *
     * Which one activates is the offering's activation_rule, not a guess:
     * on_first_payment lets the learner start on the deposit, on_full_payment
     * holds access until the balance clears.
     *
     * @return array<int, FeeLine>
     */
    private function depositAndBalance(Offering $offering): array
    {
        $deposit = $offering->deposit_cents;

        if ($deposit === null || $deposit <= 0 || $deposit >= $offering->price_cents) {
            throw new DomainException(
                "Offering [{$offering->code}] uses deposit_balance but has no usable deposit_cents.",
            );
        }

        $balance = $offering->price_cents - $deposit;
        $activatesOnDeposit = $offering->activation_rule === ActivationRule::ON_FIRST_PAYMENT;

        // Instalments are how the BALANCE is split, never how the block is
        // priced — the total is always the offering's price.
        $instalments = max(1, $offering->instalment_count ?? 1);
        $lines = [
            new FeeLine(
                sequence: 1,
                description: 'Registration fee',
                amountCents: $deposit,
                activatesEnrolment: $activatesOnDeposit,
                dueInDays: 0,
            ),
        ];

        $per = intdiv($balance, $instalments);
        $remainder = $balance - ($per * $instalments);

        for ($i = 1; $i <= $instalments; $i++) {
            $amount = $per + ($i === $instalments ? $remainder : 0);

            $lines[] = new FeeLine(
                sequence: $i + 1,
                description: $instalments === 1
                    ? 'Balance'
                    : "Balance {$i} of {$instalments}",
                amountCents: $amount,
                // When access waits for full payment, the final instalment is
                // what opens it.
                activatesEnrolment: ! $activatesOnDeposit && $i === $instalments,
                dueInDays: 30 * $i,
            );
        }

        return $lines;
    }

    /**
     * A sanity check any caller can run before charging anybody: the lines
     * must add up to the offering's price and exactly one must activate.
     *
     * @param  array<int, FeeLine>  $lines
     */
    public function assertConsistent(Offering $offering, array $lines): void
    {
        $total = array_sum(array_map(fn (FeeLine $l): int => $l->amountCents, $lines));

        if ($total !== $offering->price_cents) {
            throw new DomainException(
                "Fee schedule for [{$offering->code}] totals {$total}c but the offering is priced at {$offering->price_cents}c.",
            );
        }

        $activating = count(array_filter($lines, fn (FeeLine $l): bool => $l->activatesEnrolment));

        if ($activating !== 1) {
            throw new DomainException(
                "Fee schedule for [{$offering->code}] has {$activating} activating invoices; exactly one is required.",
            );
        }
    }
}
