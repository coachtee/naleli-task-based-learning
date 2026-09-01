<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Models\Programme;
use App\Support\CatalogueManifest;

/**
 * Which course content actually exists on this server.
 *
 * Thirteen programmes are sold; one has its content authored. That gap is
 * permanent in shape — new modules get written over months — so the system
 * has to hold it honestly rather than discover it when a learner logs in.
 *
 * Hence the distinction this class exists to draw:
 *
 *   declared   the catalogue says this programme teaches from pack X
 *   installed  pack X is on disk and its JSON parses
 *
 * A programme that is declared but not installed is a known gap: the API says
 * so plainly and the workspace tells the learner their course is not loaded
 * yet. What it must never do is quietly serve somebody else's course, which
 * is exactly what a "sensible default" would have done to a Payroll learner.
 */
class ContentPacks
{
    /** Every file a pack must have to be usable. */
    private const REQUIRED = ['course.json', 'workspace-content.json'];

    public function root(): string
    {
        return rtrim((string) config('sync.content_path'), '/');
    }

    public function isInstalled(?string $code): bool
    {
        return $code !== null && $this->problemsWith($code) === [];
    }

    /**
     * What is wrong with a pack, in words a person can act on. Empty means it
     * is fine.
     *
     * @return array<int, string>
     */
    public function problemsWith(string $code): array
    {
        if (preg_match('/^[a-z0-9-]{1,64}$/', $code) !== 1) {
            return ["\"{$code}\" is not a valid pack name (lower case, digits and hyphens only)."];
        }

        $directory = $this->root().'/'.$code;

        if (! is_dir($directory)) {
            return ["No directory at {$directory}."];
        }

        $problems = [];

        foreach (self::REQUIRED as $file) {
            $path = $directory.'/'.$file;

            if (! is_file($path)) {
                $problems[] = "{$file} is missing.";

                continue;
            }

            $decoded = json_decode((string) file_get_contents($path), true);

            if (! is_array($decoded)) {
                $problems[] = "{$file} is not valid JSON.";

                continue;
            }

            // A pack that parses but teaches nothing is worse than one that
            // fails outright: it looks installed and shows a learner an empty
            // course.
            if ($file === 'workspace-content.json') {
                $tasks = collect($decoded['workstreams'] ?? [])->sum(fn ($w): int => count($w['tasks'] ?? []));

                if ($tasks === 0) {
                    $problems[] = 'workspace-content.json has no tasks in any workstream.';
                }
            }
        }

        return $problems;
    }

    /** @return array<string, mixed>|null */
    public function load(string $code): ?array
    {
        if (! $this->isInstalled($code)) {
            return null;
        }

        $directory = $this->root().'/'.$code;
        $course = json_decode((string) file_get_contents($directory.'/course.json'), true);
        $workspace = json_decode((string) file_get_contents($directory.'/workspace-content.json'), true);

        return [
            'content_code' => $code,
            'programme_name' => $course['programmeName'] ?? $code,
            'project_title' => $course['projectTitle'] ?? null,
            'total_days' => $course['totalDays'] ?? null,
            'stages' => $course['stages'] ?? [],
            'workstreams' => $workspace['workstreams'] ?? [],
        ];
    }

    /**
     * Every programme, the pack it teaches from, and whether that pack is
     * really there. This is what `content:check` prints and what tells anyone
     * how much of the catalogue can actually be taught today.
     *
     * @return array<int, array{code: string, name: string, content_code: ?string, installed: bool, tasks: int, problems: array<int, string>}>
     */
    public function status(): array
    {
        $names = Programme::pluck('name', 'code');
        $rows = [];

        foreach (CatalogueManifest::CONTENT_PACKS as $programme => $pack) {
            $problems = $this->problemsWith($pack);

            $rows[] = [
                'code' => $programme,
                'name' => (string) ($names[$programme] ?? $programme),
                'content_code' => $pack,
                'installed' => $problems === [],
                'tasks' => $problems === [] ? $this->taskCount($pack) : 0,
                'problems' => $problems,
            ];
        }

        return $rows;
    }

    /** @return array<int, string> the packs that are really on disk */
    public function installed(): array
    {
        return array_values(array_filter(
            array_values(CatalogueManifest::CONTENT_PACKS),
            fn (string $pack): bool => $this->isInstalled($pack),
        ));
    }

    private function taskCount(string $code): int
    {
        $pack = $this->load($code);

        return collect($pack['workstreams'] ?? [])->sum(fn ($w): int => count($w['tasks'] ?? []));
    }
}
