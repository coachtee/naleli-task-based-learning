<?php

declare(strict_types=1);

namespace App\Services\Billing;

/** One invoice an offering owes, before it becomes a row. */
final class FeeLine
{
    public function __construct(
        public readonly int $sequence,
        public readonly string $description,
        public readonly int $amountCents,
        public readonly bool $activatesEnrolment,
        public readonly int $dueInDays = 0,
    ) {}
}
