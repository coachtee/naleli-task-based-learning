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

];
