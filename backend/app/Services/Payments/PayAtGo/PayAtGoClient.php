<?php

declare(strict_types=1);

namespace App\Services\Payments\PayAtGo;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The Pay@ Go (YAPI merchant) HTTP client.
 *
 * Everything provider-specific about talking to Pay@ lives here, so
 * PayAtGoProvider stays a translation between our vocabulary and theirs and
 * nothing above the PaymentProvider interface learns that Pay@ exists.
 *
 * Two behaviours of this API are load-bearing and were established against
 * the live merchant account rather than from the specification:
 *
 *   - the token endpoint wants HTTP Basic credentials, and rejects the same
 *     credentials in the POST body as `invalid_client`;
 *   - scopes must be requested explicitly, or every subsequent call 403s
 *     holding a token that looks perfectly valid.
 */
class PayAtGoClient
{
    private const TOKEN_CACHE_KEY = 'payat:access_token';

    public function __construct(private readonly CacheRepository $cache) {}

    /** No credentials means no gateway: the provider falls back to manual capture. */
    public function isConfigured(): bool
    {
        return (string) config('payat.client_id') !== ''
            && (string) config('payat.client_secret') !== '';
    }

    /**
     * Mint a payable reference.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws PayAtGoException
     */
    public function createRequestToPay(array $payload): RequestToPay
    {
        $body = $this->send(
            fn (PendingRequest $http): Response => $http->post('/merchant/rtp/create/single', $payload),
            'create a request to pay',
        );

        return RequestToPay::fromApi($body, (string) ($payload['clientAccountNumber'] ?? ''));
    }

    /**
     * What Pay@ believes about a reference right now.
     *
     * This is the authority on whether a learner has paid. The webhook only
     * tells us to come and look.
     *
     * Returns null when Pay@ has no such reference — a callback naming an
     * account number we never created, which must not be treated as an error
     * and must certainly not settle anything.
     *
     * @throws PayAtGoException
     */
    public function readRequestToPay(string $accountNumber): ?RequestToPay
    {
        $body = $this->send(
            fn (PendingRequest $http): Response => $http->get("/merchant/rtp/read/{$accountNumber}"),
            "read request to pay {$accountNumber}",
            allowMissing: true,
        );

        if ($body === []) {
            return null;
        }

        return RequestToPay::fromApi($body, $accountNumber);
    }

    /**
     * Withdraw a reference — an application cancelled before it was paid.
     *
     * @throws PayAtGoException
     */
    public function cancelRequestToPay(string $accountNumber): bool
    {
        $this->send(
            fn (PendingRequest $http): Response => $http->put("/merchant/rtp/cancel/single/{$accountNumber}"),
            "cancel request to pay {$accountNumber}",
        );

        return true;
    }

    /**
     * @param  callable(PendingRequest): Response  $call
     * @return array<string, mixed>
     *
     * @throws PayAtGoException
     */
    private function send(callable $call, string $description, bool $allowMissing = false): array
    {
        $response = $this->attempt($call, $this->token());

        // A cached token can be revoked or rotated at Pay@ while we still hold
        // it. One retry on a fresh token distinguishes "our token is stale"
        // from "we are not allowed to do this", which otherwise look alike.
        if ($response->status() === 401 || $response->status() === 403) {
            $this->cache->forget(self::TOKEN_CACHE_KEY);
            $response = $this->attempt($call, $this->token());
        }

        if ($allowMissing && $response->status() === 404) {
            return [];
        }

        if ($response->failed()) {
            throw new PayAtGoException(
                "Pay@ Go refused to {$description}: HTTP {$response->status()}.",
                $response->status(),
                $this->decode($response),
            );
        }

        return $this->decode($response);
    }

    /**
     * @param  callable(PendingRequest): Response  $call
     */
    private function attempt(callable $call, string $token): Response
    {
        return $call(
            Http::baseUrl(rtrim((string) config('payat.base_url'), '/'))
                ->withToken($token)
                ->acceptJson()
                ->asJson()
                ->timeout((int) config('payat.timeout', 20)),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        try {
            $decoded = $response->json();
        } catch (Throwable) {
            return ['body' => $response->body()];
        }

        return is_array($decoded) ? $decoded : ['body' => $response->body()];
    }

    /**
     * A client-credentials token, cached until just before it expires.
     *
     * @throws PayAtGoException
     */
    private function token(): string
    {
        $cached = $this->cache->get(self::TOKEN_CACHE_KEY);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        if (! $this->isConfigured()) {
            throw new PayAtGoException('Pay@ Go has no client credentials configured.');
        }

        $response = Http::asForm()
            ->withBasicAuth((string) config('payat.client_id'), (string) config('payat.client_secret'))
            ->timeout((int) config('payat.timeout', 20))
            ->post((string) config('payat.token_url'), [
                'grant_type' => 'client_credentials',
                'scope' => implode(' ', (array) config('payat.scopes', [])),
            ]);

        if ($response->failed()) {
            throw new PayAtGoException(
                "Pay@ Go refused the client credentials: HTTP {$response->status()}.",
                $response->status(),
                $this->decode($response),
            );
        }

        $token = (string) ($response->json('access_token') ?? '');

        if ($token === '') {
            throw new PayAtGoException('Pay@ Go returned no access token.', $response->status(), $this->decode($response));
        }

        // Sixty seconds of headroom: a token that expires mid-flight fails a
        // learner's payment lookup for no reason.
        $ttl = max(60, (int) ($response->json('expires_in') ?? 3600) - 60);

        $this->cache->put(self::TOKEN_CACHE_KEY, $token, $ttl);

        return $token;
    }
}
