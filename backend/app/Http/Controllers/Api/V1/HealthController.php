<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    /** Unauthenticated liveness check. Deliberately reveals nothing else. */
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'service' => 'kcs-education-backend',
            'api_version' => 'v1',
            'time' => now()->toIso8601String(),
        ]);
    }
}
