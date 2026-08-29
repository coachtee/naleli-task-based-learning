<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\InboundWebhook;
use App\Services\Intake\ApplicationIntake;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Receives the existing Student Application Form from kcs.edu.za.
 *
 * Every delivery is logged before it is interpreted, so a submission that
 * fails to process is still recoverable from our own records rather than lost
 * between two systems.
 */
class FluentFormsApplicationController extends Controller
{
    public function __construct(private readonly ApplicationIntake $intake) {}

    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->all();

        $delivery = InboundWebhook::create([
            'source' => 'fluentform',
            'event_type' => 'application.submitted',
            'external_id' => isset($payload['submission_id']) ? (string) $payload['submission_id'] : null,
            'signature_valid' => true,   // the middleware refused everything else
            'payload' => $payload,
            'received_at' => now(),
        ]);

        try {
            $result = $this->intake->receive($payload, $delivery);
        } catch (Throwable $e) {
            // Answer 200 with an explicit failure rather than 500: Fluent Forms
            // would retry a 500 indefinitely, and the delivery is already
            // safely on record for a human to replay once the cause is fixed.
            $delivery->update(['processing_error' => $e->getMessage()]);

            report($e);

            return response()->json([
                'status' => 'received_with_error',
                'delivery_id' => $delivery->id,
                'message' => 'Recorded for review.',
            ]);
        }

        $application = $result['application'];

        return response()->json([
            'status' => $result['created'] ? 'created' : 'duplicate_ignored',
            'learner_ref' => $application->learner->learner_ref,
            'application_id' => $application->id,
            'programme_code' => $application->programme?->code,
        ], $result['created'] ? 201 : 200);
    }
}
