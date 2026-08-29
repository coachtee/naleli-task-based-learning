<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\ProgrammeStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProgrammeResource;
use App\Models\Programme;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProgrammeController extends Controller
{
    /** The public catalogue. No learner data, so no authentication. */
    public function index(): AnonymousResourceCollection
    {
        return ProgrammeResource::collection(
            Programme::where('status', ProgrammeStatus::OPEN)
                ->orderBy('sort_order')
                ->get(),
        );
    }
}
