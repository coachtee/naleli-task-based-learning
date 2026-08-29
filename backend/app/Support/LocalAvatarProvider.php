<?php

declare(strict_types=1);

namespace App\Support;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Initials, drawn as an inline SVG data URI.
 *
 * Filament's default provider calls an external avatar service, which sends
 * staff names to a third party and renders as a broken image anywhere
 * outbound traffic is restricted — including a school network. Nothing here
 * leaves the server.
 */
class LocalAvatarProvider implements AvatarProvider
{
    public function get(Model|Authenticatable $record): string
    {
        $name = trim((string) ($record->getAttribute('name') ?? ''));

        $initials = collect(preg_split('/\s+/', $name) ?: [])
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');

        if ($initials === '') {
            $initials = '?';
        }

        $svg = <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" width="64" height="64">
            <rect width="64" height="64" rx="32" fill="#0A1140"/>
            <text x="32" y="41" text-anchor="middle" font-family="Archivo, Helvetica, Arial, sans-serif"
                  font-size="24" font-weight="600" fill="#FFFFFF">{$initials}</text>
        </svg>
        SVG;

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
