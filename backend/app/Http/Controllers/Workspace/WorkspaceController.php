<?php

declare(strict_types=1);

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Services\Identity\LabPin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * The learner workspace as an installable web app.
 *
 * Served from here rather than as files in `public/` for one reason: the
 * application's front controller lives in `public_html/admin`, so anything
 * static under it would answer at a URL that reads like a staff area, and
 * Apache would hand a real directory back before Laravel ever saw the
 * request. Routes keep every workspace URL under one path the website can
 * rewrite, exactly as it already rewrites `/my/...` for profile links.
 *
 * A service worker only controls pages inside its own scope, so the shell is
 * `/workspace/` and the worker is `/workspace/sw.js` — same directory, so the
 * worker controls the app and nothing else on the domain.
 */
class WorkspaceController extends Controller
{
    /**
     * The address a learner actually opens, not the one behind the rewrite.
     *
     * Unset locally, where the app is served from the project root and
     * `url()` is already right. Production sets KCS_WORKSPACE_URL because the
     * front controller sits in public_html/admin and `url()` would put
     * /admin/ into every learner-facing URL — which breaks installing the app,
     * not just the look of it.
     */
    private function base(): string
    {
        $configured = (string) config('kcs.workspace_url');

        return rtrim($configured !== '' ? $configured : url('/workspace'), '/');
    }

    /**
     * Where the page sends its requests. Never derived from the current
     * request: this page is reached through a rewrite, so `url()` reports a
     * root of "/" and would send every call to a 404.
     */
    private function api(): string
    {
        $configured = (string) config('kcs.api_url');

        return rtrim($configured !== '' ? $configured : url('/api/v1'), '/');
    }

    public function shell(): View
    {
        return view('workspace.shell', [
            'config' => [
                'api' => $this->api(),
                'base' => $this->base(),
                'pinLength' => LabPin::LENGTH,
                // A learner who walks away without logging out must not leave
                // the next one holding their session.
                'idleMinutes' => 20,
            ],
        ]);
    }

    public function serviceWorker(): Response
    {
        return response()
            ->view('workspace.sw', ['version' => $this->version(), 'base' => $this->base()])
            ->header('Content-Type', 'application/javascript')
            ->header('Cache-Control', 'no-cache')
            // Belt and braces: lets the worker claim the whole path even if a
            // rewrite ever serves it from somewhere shallower.
            ->header('Service-Worker-Allowed', '/');
    }

    public function manifest(): JsonResponse
    {
        return response()->json([
            'name' => 'Naleli Workspace — KCS',
            'short_name' => 'Naleli',
            'description' => 'Your course work, at the lab or at home.',
            'start_url' => $this->base().'/',
            'scope' => $this->base().'/',
            'display' => 'standalone',
            'orientation' => 'any',
            'background_color' => '#0B1F3A',
            'theme_color' => '#0B1F3A',
            'icons' => [
                [
                    'src' => $this->base().'/icon.svg',
                    'type' => 'image/svg+xml',
                    'sizes' => 'any',
                    'purpose' => 'any maskable',
                ],
            ],
        ])->header('Content-Type', 'application/manifest+json');
    }

    public function icon(): Response
    {
        $svg = <<<'SVG'
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
              <rect width="512" height="512" fill="#0B1F3A"/>
              <path d="M150 360V152h40l132 132V152h40v208h-40L190 228v132z" fill="#fff"/>
              <rect x="150" y="386" width="212" height="14" rx="7" fill="#E8613C"/>
            </svg>
            SVG;

        return response($svg)
            ->header('Content-Type', 'image/svg+xml')
            ->header('Cache-Control', 'public, max-age=86400');
    }

    /**
     * Changes whenever the shell or the worker changes, so a lab PC that has
     * been running the app all term picks up a new version instead of serving
     * a cached one for ever.
     */
    private function version(): string
    {
        $files = [
            resource_path('views/workspace/shell.blade.php'),
            resource_path('views/workspace/sw.blade.php'),
        ];

        $stamp = '';
        foreach ($files as $file) {
            $stamp .= is_file($file) ? (string) filemtime($file) : '0';
        }

        return substr(hash('sha256', $stamp), 0, 12);
    }
}
