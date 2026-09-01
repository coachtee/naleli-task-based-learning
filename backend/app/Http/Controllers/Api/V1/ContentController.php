<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The course content pack.
 *
 * The Android app carries this inside the APK; a browser cannot, so it is
 * served instead and the service worker caches it. It is the same JSON either
 * way — one source of content, not two that can drift.
 *
 * Content is not learner data: no authentication, an ETag so a client that
 * already has it re-downloads nothing, and it may be cached by anything in
 * between.
 */
class ContentController extends Controller
{
    public function __invoke(Request $request, string $code): JsonResponse
    {
        // Whitelisted rather than escaped. `code` names a directory, and the
        // set of valid content packs is small and known.
        if (preg_match('/^[a-z0-9-]{1,64}$/', $code) !== 1) {
            throw new NotFoundHttpException('No such content pack.');
        }

        $directory = rtrim((string) config('sync.content_path'), '/').'/'.$code;

        $workspace = $this->read($directory.'/workspace-content.json');
        $course = $this->read($directory.'/course.json');

        if ($workspace === null || $course === null) {
            throw new NotFoundHttpException("Content pack {$code} is not installed on this server.");
        }

        $payload = [
            'content_code' => $code,
            'programme_name' => $course['programmeName'] ?? $code,
            'project_title' => $course['projectTitle'] ?? null,
            'total_days' => $course['totalDays'] ?? null,
            'stages' => $course['stages'] ?? [],
            'workstreams' => $workspace['workstreams'] ?? [],
        ];

        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $etag = '"'.substr(hash('sha256', $body), 0, 32).'"';

        if (trim((string) $request->header('If-None-Match')) === $etag) {
            return response()->json(null, 304)->setEtag($etag);
        }

        return response()->json($payload)
            ->setEtag($etag)
            ->header('Cache-Control', 'public, max-age=300');
    }

    /** @return array<string, mixed>|null */
    private function read(string $path): ?array
    {
        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : null;
    }
}
