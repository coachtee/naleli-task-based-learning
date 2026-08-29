<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\ProgrammeController;
use App\Http\Controllers\Api\V1\TokenActivationController;
use App\Http\Controllers\Webhooks\FluentFormsApplicationController;
use Illuminate\Support\Facades\Route;

/*
 * The Naleli Workspace v1 contract.
 *
 * Everything the Android app is allowed to reach lives under /api/v1. The
 * app is authoritative about what a learner DID; this backend is
 * authoritative about what that COUNTS FOR — so entitlements, assessment
 * results and certificates are read-only here and have no write route at all.
 *
 * Phase 1 ships activation, profile, entitlements and the catalogue. Progress
 * and evidence sync arrive in Phase 3; assessment results and certificates in
 * Phases 4 and 5. The app is not connected to any of this yet.
 */

Route::prefix('v1')->group(function (): void {

    // --- public --------------------------------------------------------
    Route::get('health', HealthController::class);
    Route::get('programmes', [ProgrammeController::class, 'index']);
    Route::post('tokens/activate', [TokenActivationController::class, 'store']);

    // --- the website's application webhook -----------------------------
    Route::post('intake/application', FluentFormsApplicationController::class)
        ->middleware('webhook.signature:fluentform');

    // --- authenticated as a learner device -----------------------------
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('me', [MeController::class, 'show']);
        Route::get('me/entitlements', [MeController::class, 'entitlements']);
    });
});
