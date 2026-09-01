<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\EntitlementResource;
use App\Http\Resources\LearnerResource;
use App\Models\Learner;
use App\Services\Identity\LabPin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Logging in and out at a shared computer.
 *
 * Three learners use the same machine in a day. That is the whole design
 * problem: nothing may be left behind by the one who just left, and nothing
 * the next one does may be filed under the wrong name. So this is a session,
 * not an activation — the token it issues expires on its own and is destroyed
 * on logout, and the client holds it in memory rather than in storage.
 *
 * The phone keeps its own front door (`POST /tokens/activate`). One handset
 * belongs to one learner for good, and asking them to type a PIN every
 * morning to reach work they already have on the device would be theatre.
 */
class LabSessionController extends Controller
{
    /** How long a forgotten session stays usable. One class is two hours. */
    private const HOURS = 12;

    public function __construct(private readonly LabPin $pins) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'learner_ref' => ['required', 'string', 'max:32'],
            'pin' => ['required', 'string', 'max:12'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $reference = strtoupper(trim($data['learner_ref']));

        // Learner references run in sequence, so they are guessable and the
        // PIN is the only real secret. Limit by reference as well as by
        // address: one lab shares one address, and rate limiting the whole
        // room because of one wrong PIN would lock out the class.
        $limits = [
            'lab-login:ref:'.$reference => 5,
            'lab-login:ip:'.$request->ip() => 30,
        ];

        foreach ($limits as $key => $allowed) {
            if (RateLimiter::tooManyAttempts($key, $allowed)) {
                throw ValidationException::withMessages([
                    'pin' => 'Too many attempts. Please wait a minute, then try again.',
                ])->status(429);
            }
        }

        $learner = Learner::where('learner_ref', $reference)->first();

        // One message for "no such learner" and "wrong PIN". Telling them
        // apart turns a guessable reference into a way to enumerate the roll.
        if ($learner === null || ! $this->pins->verify($learner, $data['pin'])) {
            foreach ($limits as $key => $allowed) {
                RateLimiter::hit($key, decaySeconds: 60);
            }

            throw ValidationException::withMessages([
                'pin' => 'That student number and PIN do not match. Ask your facilitator if you are stuck.',
            ]);
        }

        foreach (array_keys($limits) as $key) {
            RateLimiter::clear($key);
        }

        $expiresAt = now()->addHours(self::HOURS);

        $session = $learner->createToken(
            name: $data['device_name'] ?? 'Lab session',
            abilities: ['learner'],
            expiresAt: $expiresAt,
        );

        return response()->json([
            'token' => $session->plainTextToken,
            'expires_at' => $expiresAt->toIso8601String(),
            'learner' => new LearnerResource($learner),
            'entitlements' => EntitlementResource::collection(
                $learner->entitlements()->with('programme')->get(),
            ),
        ], 201);
    }

    /**
     * Log out. Destroys this session only — the learner's phone keeps its own
     * token, because logging off a lab PC is not a reason to sign somebody
     * out of their own handset.
     */
    public function destroy(Request $request): Response
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->noContent();
    }
}
