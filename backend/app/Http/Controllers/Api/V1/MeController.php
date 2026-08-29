<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\EntitlementResource;
use App\Http\Resources\LearnerResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MeController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'learner' => new LearnerResource($request->user()),
        ]);
    }

    /**
     * The only thing the app reads to decide what to show. Recomputed
     * server-side from enrolments and payments, so a phone cannot grant itself
     * access to a programme by editing anything locally.
     */
    public function entitlements(Request $request): AnonymousResourceCollection
    {
        return EntitlementResource::collection(
            $request->user()->entitlements()->with('programme')->get(),
        );
    }
}
