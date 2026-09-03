<?php

declare(strict_types=1);

namespace App\Support;

/**
 * A link into the Filament panel, correct no matter how the current request
 * arrived.
 *
 * `route()` resolves a name to a path fine on its own; the domain and base
 * path it prepends are inferred from the current request — the common
 * prefix between REQUEST_URI and SCRIPT_NAME. That is right for a request
 * that hit /admin/... directly, and wrong for one WordPress rewrote onto a
 * bare path like /calls: REQUEST_URI never becomes /admin/calls, so
 * Laravel infers no base at all, and a link built with route() from that
 * request comes back missing /admin entirely.
 *
 * Every staff.* mobile page is reached exactly that way, so a link from one
 * of them INTO the Filament panel has its root stated here rather than left
 * to be inferred — the same reasoning as KCS_WORKSPACE_URL and KCS_API_URL.
 * staff.* routes themselves need no such treatment: reached the same way,
 * the inferred empty base is already the right answer for them.
 */
class AdminUrl
{
    /** @param  array<string, mixed>  $parameters */
    public static function route(string $name, array $parameters = []): string
    {
        return rtrim((string) config('app.url'), '/').route($name, $parameters, absolute: false);
    }
}
