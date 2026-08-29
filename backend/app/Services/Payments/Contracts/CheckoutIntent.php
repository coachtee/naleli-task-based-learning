<?php

declare(strict_types=1);

namespace App\Services\Payments\Contracts;

/** Where to send a learner to pay, and the reference we will recognise them by. */
final class CheckoutIntent
{
    public function __construct(
        public readonly string $providerReference,
        public readonly ?string $redirectUrl = null,
        /** @var array<string, mixed> */
        public readonly array $payload = [],
    ) {}
}
