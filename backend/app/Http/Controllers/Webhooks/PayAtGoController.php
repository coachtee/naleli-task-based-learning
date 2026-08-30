<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\InboundWebhook;
use App\Models\Invoice;
use App\Services\Enrolment\EnrolmentActivator;
use App\Services\Payments\Providers\PayAtGoProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Pay@ Go's payment notification.
 *
 * There is no signature to check — Pay@ does not sign this callback — so this
 * endpoint is deliberately built to be safe when called by a stranger. It
 * takes exactly one thing from the request body, the identity of a reference,
 * and then decides everything else by reading that reference back from Pay@
 * over an authenticated connection. A forged callback therefore settles
 * nothing: at worst it costs us one API call.
 *
 * Every delivery is recorded before it is interpreted, genuine or not, so a
 * payment that fails to process is recoverable from our own records.
 */
class PayAtGoController extends Controller
{
    public function __construct(
        private readonly PayAtGoProvider $provider,
        private readonly EnrolmentActivator $activator,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->all();

        $delivery = InboundWebhook::create([
            'source' => 'payat_go',
            'event_type' => 'rtp.notification',
            'external_id' => $this->externalId($payload),
            // Not a signature: this records that Pay@'s own API confirmed the
            // reference, which is the only verification available here.
            'signature_valid' => false,
            'payload' => $payload,
            'received_at' => now(),
        ]);

        try {
            $result = $this->provider->verifyCallback($request);
        } catch (Throwable $e) {
            $delivery->update(['processing_error' => $e->getMessage()]);

            report($e);

            // 200, not 500: Pay@ would retry a 500 and the delivery is already
            // on record. A reconciliation read catches it either way.
            return response()->json([
                'status' => 'received_with_error',
                'delivery_id' => $delivery->id,
            ]);
        }

        if ($result === null) {
            $delivery->update([
                'processed_at' => now(),
                'processing_error' => 'No Pay@ reference of ours could be identified.',
            ]);

            return response()->json(['status' => 'ignored', 'delivery_id' => $delivery->id]);
        }

        $delivery->update(['signature_valid' => true]);

        $invoice = Invoice::where('payat_account_number', $result->providerReference)->first();

        // Same reason as the reconcile sweep: keep the last state Pay@ reported
        // on the invoice so a registrar sees it without another API call.
        $invoice?->forceFill(['payat_state' => $result->raw['account_state'] ?? null])->save();

        // Nothing has been paid yet — Pay@ notifies on states other than
        // payment too. Recording a zero-rand payment row would be noise.
        if ($result->status !== PaymentStatus::SETTLED && $result->amountCents <= 0) {
            $delivery->update(['processed_at' => now(), 'related_type' => Invoice::class, 'related_id' => $invoice?->id]);

            return response()->json(['status' => 'no_payment', 'delivery_id' => $delivery->id]);
        }

        if ($invoice === null) {
            $delivery->update([
                'processed_at' => now(),
                'processing_error' => "Pay@ reference {$result->providerReference} matches no invoice.",
            ]);

            return response()->json(['status' => 'unmatched', 'delivery_id' => $delivery->id]);
        }

        try {
            $settled = $this->activator->settle($result, $invoice);
        } catch (Throwable $e) {
            $delivery->update(['processing_error' => $e->getMessage()]);

            report($e);

            return response()->json(['status' => 'received_with_error', 'delivery_id' => $delivery->id]);
        }

        $delivery->update([
            'processed_at' => now(),
            'related_type' => $settled['payment']::class,
            'related_id' => $settled['payment']->id,
        ]);

        return response()->json([
            'status' => $settled['already_settled'] ? 'already_settled' : $result->status->value,
            'delivery_id' => $delivery->id,
            'invoice_id' => $invoice->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function externalId(array $payload): ?string
    {
        foreach (['requestToPayId', 'sourceReference', 'clientAccountNumber'] as $key) {
            if (isset($payload[$key]) && is_scalar($payload[$key]) && (string) $payload[$key] !== '') {
                return substr((string) $payload[$key], 0, 120);
            }
        }

        return null;
    }
}
