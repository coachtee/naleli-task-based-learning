<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\EvidenceController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\ProgrammeController;
use App\Http\Controllers\Api\V1\ProgressController;
use App\Http\Controllers\Api\V1\TokenActivationController;
use App\Http\Controllers\Webhooks\FluentFormsApplicationController;
use App\Http\Controllers\Webhooks\PayAtGoController;
use Illuminate\Support\Facades\Route;

/*
 * The Naleli Workspace v1 contract.
 *
 * Everything the Android app is allowed to reach lives under /api/v1. The
 * app is authoritative about what a learner DID; this backend is
 * authoritative about what that COUNTS FOR — so entitlements, assessment
 * results and certificates are read-only here and have no write route at all.
 *
 * Phase 1 ships activation, profile, entitlements and the catalogue. Phase 3
 * adds the learning record — progress and evidence sync — which is what lets
 * the same learner work on a phone at home and a lab PC at KCS and see one
 * body of work. Assessment results and certificates follow in Phases 4 and 5.
 * No client is connected to any of this yet.
 */

Route::prefix('v1')->group(function (): void {

    // --- public --------------------------------------------------------
    Route::get('health', HealthController::class);
    Route::get('programmes', [ProgrammeController::class, 'index']);
    Route::post('tokens/activate', [TokenActivationController::class, 'store']);

    // --- the website's application webhook -----------------------------
    Route::post('intake/application', FluentFormsApplicationController::class)
        ->middleware('webhook.signature:fluentform');

    // --- Pay@ Go's payment notification --------------------------------
    // No signature: Pay@ does not sign this callback. The controller takes
    // only the identity of a reference from the body and settles from an
    // authenticated read against Pay@, so an unsigned or forged call cannot
    // move money. The throttle is there because the endpoint is public.
    Route::post('payments/payat', PayAtGoController::class)
        ->middleware('throttle:60,1');

    // --- authenticated as a learner device -----------------------------
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('me', [MeController::class, 'show']);
        Route::get('me/entitlements', [MeController::class, 'entitlements']);

        // --- the learning record ---------------------------------------
        // The learner account owns the work; a phone and a lab PC are only
        // working copies. These four routes are how a copy catches up with
        // the record and hands its own changes over. GET and POST return
        // the identical shape, so a client can replace its local state with
        // whatever comes back and never has to merge twice.
        Route::middleware('throttle:120,1')->group(function (): void {
            Route::get('me/progress', [ProgressController::class, 'show']);
            Route::post('me/progress', [ProgressController::class, 'store']);

            Route::post('me/evidence', [EvidenceController::class, 'store']);
            Route::get('me/evidence/{evidence}', [EvidenceController::class, 'show'])
                ->where('evidence', '[A-Za-z0-9._-]{1,64}')
                ->name('api.v1.me.evidence.show');
        });
    });
});
