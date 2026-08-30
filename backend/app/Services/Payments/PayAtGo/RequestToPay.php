<?php

declare(strict_types=1);

namespace App\Services\Payments\PayAtGo;

use App\Enums\PayAtAccountState;

/**
 * One payable reference at Pay@, as we care about it.
 *
 * Pay@ returns thirty-odd fields; these are the seven that decide anything.
 * Money is cents on both sides of this boundary, so nothing is converted.
 */
final class RequestToPay
{
    public function __construct(
        public readonly string $accountNumber,
        public readonly ?string $requestToPayId,
        public readonly ?string $sourceReference,
        public readonly ?string $paymentLink,
        public readonly int $amountCents,
        public readonly int $amountPaidCents,
        public readonly ?PayAtAccountState $state,
        /** @var array<string, mixed> */
        public readonly array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $body
     */
    public static function fromApi(array $body, string $fallbackAccountNumber = ''): self
    {
        return new self(
            accountNumber: (string) ($body['clientAccountNumber'] ?? $fallbackAccountNumber),
            requestToPayId: isset($body['requestToPayId']) ? (string) $body['requestToPayId'] : null,
            sourceReference: isset($body['sourceReference']) ? (string) $body['sourceReference'] : null,
            paymentLink: isset($body['paymentLink']) ? (string) $body['paymentLink'] : null,
            amountCents: (int) ($body['amount'] ?? 0),
            amountPaidCents: (int) ($body['amountPaid'] ?? 0),
            state: PayAtAccountState::tryFromApi($body['accountState'] ?? null),
            raw: $body,
        );
    }

    /**
     * Paid in full, and Pay@ agrees it is paid.
     *
     * Both halves matter. A state of PAYMENT_COMPLETED against an amountPaid
     * short of the invoice is not settlement, and an amountPaid that covers
     * the invoice while the state is still PROCESSING_PAYMENT is not money we
     * can spend yet.
     */
    public function isSettled(): bool
    {
        return $this->state?->isSettled() === true
            && $this->amountPaidCents >= $this->amountCents
            && $this->amountCents > 0;
    }

    public function hasMoney(): bool
    {
        return $this->amountPaidCents > 0;
    }
}
