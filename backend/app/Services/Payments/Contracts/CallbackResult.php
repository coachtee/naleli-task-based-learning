<?php

declare(strict_types=1);

namespace App\Services\Payments\Contracts;

use App\Enums\PaymentStatus;

/** What a provider says happened, normalised. */
final class CallbackResult
{
    public function __construct(
        public readonly string $provider,
        public readonly string $providerReference,
        public readonly PaymentStatus $status,
        public readonly int $amountCents,
        /** @var array<string, mixed> */
        public readonly array $raw = [],
    ) {}

    public function isSettled(): bool
    {
        return $this->status === PaymentStatus::SETTLED;
    }
}
