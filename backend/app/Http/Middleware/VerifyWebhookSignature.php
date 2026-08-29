<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * An HMAC over the raw request body, compared in constant time.
 *
 * The intake endpoint creates learner records from an unauthenticated public
 * request, so without this anyone who found the URL could fill the database
 * with applications. The signature is the only thing standing between the
 * open internet and the learner table.
 */
class VerifyWebhookSignature
{
    public function handle(Request $request, Closure $next, string $source): Response
    {
        $secret = (string) config("webhooks.{$source}.secret");

        if ($secret === '') {
            // Failing closed: an unconfigured secret must never mean "allow
            // everything", which is what an empty-string comparison would do.
            abort(Response::HTTP_SERVICE_UNAVAILABLE, "Webhook source [{$source}] is not configured.");
        }

        $provided = (string) $request->header('X-KCS-Signature');
        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        if (! hash_equals($expected, $provided)) {
            abort(Response::HTTP_UNAUTHORIZED, 'Invalid webhook signature.');
        }

        return $next($request);
    }
}
