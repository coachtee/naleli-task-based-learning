<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\EntitlementResource;
use App\Http\Resources\LearnerResource;
use App\Services\Tokens\AccessTokenIssuer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * The app's front door: a learner types or scans their access token once, and
 * the phone is issued a long-lived device token in exchange.
 *
 * The access token is programme access; the device token is how this phone
 * authenticates from then on. Keeping them separate is what lets the same
 * learner activate a second programme later without re-registering, and lets
 * a lost phone be cut off without touching the learner's identity.
 */
class TokenActivationController extends Controller
{
    public function __construct(private readonly AccessTokenIssuer $issuer) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:64'],
            'device_name' => ['required', 'string', 'max:120'],
            'platform' => ['nullable', 'string', 'max:32'],
            'app_version' => ['nullable', 'string', 'max:32'],
        ]);

        // Tokens are short and typed by hand, so brute force is a real concern.
        $throttleKey = 'activate:'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, maxAttempts: 10)) {
            throw ValidationException::withMessages([
                'token' => 'Too many attempts. Please wait a minute and try again.',
            ])->status(429);
        }

        $accessToken = $this->issuer->redeem($data['token']);

        if ($accessToken === null) {
            RateLimiter::hit($throttleKey, decaySeconds: 60);

            throw ValidationException::withMessages([
                'token' => 'That access token is not valid, has already been used, or has been revoked.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        $learner = $accessToken->learner;

        $device = $learner->createToken(
            name: $data['device_name'],
            abilities: ['learner'],
        );

        return response()->json([
            'device_token' => $device->plainTextToken,
            'learner' => new LearnerResource($learner),
            'entitlements' => EntitlementResource::collection(
                $learner->entitlements()->with('programme')->get(),
            ),
            'activated_programme' => $accessToken->enrolment->programme->code,
        ], 201);
    }
}
