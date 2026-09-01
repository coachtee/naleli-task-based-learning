<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Services\Content\ContentPacks;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What the app may open, and under which content pack.
 *
 * `content_code` is what binds an entitlement to the lesson content already
 * bundled in the APK, so the app never has to map a programme name to a
 * content file itself.
 */
class EntitlementResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'programme_code' => $this->programme->code,
            'programme_name' => $this->programme->name,
            'tier' => $this->programme->tier->value,
            'content_code' => $this->programme->content_code,
            'content_version' => $this->programme->content_version,
            // Whether that pack is actually on this server. Thirteen
            // programmes are sold and their content is authored over months,
            // so a client must be able to say "your course is not loaded yet"
            // rather than guess at a pack and show somebody else's course.
            'content_installed' => app(ContentPacks::class)
                ->isInstalled($this->programme->content_code),
            'state' => $this->state->value,
            'reason' => $this->reason,
            'unlocked_at' => $this->unlocked_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
        ];
    }
}
