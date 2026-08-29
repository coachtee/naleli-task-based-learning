<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Services\Payments\Contracts\PaymentProvider;
use InvalidArgumentException;

/**
 * Resolves a provider by its key. Ozow, PayJustNow and Payflex become entries
 * in config/payments.php once their merchant accounts are approved and their
 * integration documents arrive — no other file changes.
 */
class PaymentProviderRegistry
{
    /** @var array<string, PaymentProvider> */
    private array $providers = [];

    /**
     * @param  iterable<PaymentProvider>  $providers
     */
    public function __construct(iterable $providers = [])
    {
        foreach ($providers as $provider) {
            $this->providers[$provider->key()] = $provider;
        }
    }

    public function get(string $key): PaymentProvider
    {
        return $this->providers[$key]
            ?? throw new InvalidArgumentException("Unknown payment provider [{$key}].");
    }

    public function has(string $key): bool
    {
        return isset($this->providers[$key]);
    }

    /** @return array<string, string> key => label, for dashboard dropdowns */
    public function options(): array
    {
        return array_map(fn (PaymentProvider $p): string => $p->label(), $this->providers);
    }
}
