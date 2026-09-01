<?php

declare(strict_types=1);

return [

    /*
     * Where the Filament panel is mounted, relative to whatever directory the
     * front controller sits in.
     *
     * Locally the app is served from the project root, so the panel lives at
     * /admin. In production the front controller is inside public_html/admin,
     * which already supplies that segment — leaving this as 'admin' there
     * would put the dashboard at kcs.edu.za/admin/admin. Setting
     * FILAMENT_PANEL_PATH="" in production mounts it at the directory root, so
     * staff reach it at kcs.edu.za/admin and the API sits alongside it at
     * kcs.edu.za/admin/api/v1/...
     */
    'panel_path' => env('FILAMENT_PANEL_PATH', 'admin'),

    /*
     * The address a learner sees. The application is served from /admin, so a
     * raw link reads like a staff area to somebody who was sent it — and a
     * link that looks like an admin panel is a link people do not click. The
     * website rewrites /my/... onto the application.
     */
    'public_url' => env('KCS_PUBLIC_URL', 'https://www.kcs.edu.za'),

    /*
     * Where a learner reaches the workspace.
     *
     * Same problem as the profile links, with one extra constraint: an
     * installable web app is only installable when the manifest's `scope`
     * matches the URL the page is actually served from. Generate
     * /admin/workspace/ URLs into a page the learner opened at /workspace/
     * and the browser refuses to install it — "page not in scope" — with no
     * visible error. So every workspace URL is built from the public address,
     * and the website rewrites that path onto the application internally
     * (a redirect would put /admin back in the learner's address bar).
     */
    'workspace_url' => env('KCS_WORKSPACE_URL'),

];
