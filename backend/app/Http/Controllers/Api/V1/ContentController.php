<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Content\ContentPacks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The course content packs.
 *
 * The Android app carries its pack inside the APK; a browser cannot, so it is
 * served instead and the service worker caches it. Same JSON either way — one
 * source of content, not two that can drift.
 *
 * Content is not learner data: no authentication, an ETag so a client that
 * already has a pack downloads nothing, and cacheable by anything in between.
 */
class ContentController extends Controller
{
    public function __construct(private readonly ContentPacks $packs) {}

    /** What this server can actually teach today. */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => collect($this->packs->status())
                ->map(fn (array $row): array => [
                    'programme_code' => $row['code'],
                    'programme_name' => $row['name'],
                    'content_code' => $row['content_code'],
                    'installed' => $row['installed'],
                    'tasks' => $row['tasks'],
                ])
                ->values(),
        ]);
    }

    public function show(Request $request, string $code): JsonResponse
    {
        $pack = $this->packs->load($code);

        if ($pack === null) {
            // Deliberately the same answer for "no such pack" and "declared
            // but nobody has written it yet". The client's job is to say the
            // course is not loaded; which of the two it is belongs in
            // `content:check`, not in a learner's error message.
            throw new NotFoundHttpException("Content pack {$code} is not installed on this server.");
        }

        $body = json_encode($pack, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $etag = '"'.substr(hash('sha256', $body), 0, 32).'"';

        if (trim((string) $request->header('If-None-Match')) === $etag) {
            return response()->json(null, 304)->setEtag($etag);
        }

        return response()->json($pack)
            ->setEtag($etag)
            ->header('Cache-Control', 'public, max-age=300');
    }
}
