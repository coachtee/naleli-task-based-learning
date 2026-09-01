<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Content\ContentPacks;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * How much of the catalogue can actually be taught today.
 *
 * Thirteen programmes are sold and the content for each is written over
 * months, so "which ones are ready" is a standing question rather than a
 * one-off. Run this after adding a pack, and in the deployment check — a pack
 * whose JSON stopped parsing fails here rather than in front of a class.
 */
class CheckContentPacks extends Command
{
    protected $signature = 'content:check {--strict : Fail if any declared pack is missing}';

    protected $description = 'Report which programmes have their course content installed';

    public function handle(ContentPacks $packs): int
    {
        $rows = $packs->status();

        $this->newLine();
        $this->line('  Content packs read from <options=bold>'.$packs->root().'</>');
        $this->newLine();

        $this->table(
            ['Programme', 'Content pack', 'Status', 'Tasks'],
            array_map(fn (array $row): array => [
                $row['code'].'  '.Str::limit($row['name'], 38),
                $row['content_code'] ?? '—',
                $row['installed'] ? 'ready' : 'not written yet',
                $row['installed'] ? (string) $row['tasks'] : '—',
            ], $rows),
        );

        $ready = array_values(array_filter($rows, fn (array $r): bool => $r['installed']));
        $missing = array_values(array_filter($rows, fn (array $r): bool => ! $r['installed']));

        $this->line(sprintf('  <options=bold>%d of %d</> programmes can be taught.', count($ready), count($rows)));

        // A pack that exists but is broken is a different problem from one
        // nobody has written, and only the first is urgent.
        $broken = array_filter($missing, fn (array $r): bool => ! str_contains($r['problems'][0] ?? '', 'No directory at'));

        foreach ($broken as $row) {
            $this->newLine();
            $this->error("  {$row['content_code']} is installed but unusable:");
            foreach ($row['problems'] as $problem) {
                $this->line("    - {$problem}");
            }
        }

        $this->newLine();

        if ($broken !== []) {
            return self::FAILURE;
        }

        return ($this->option('strict') && $missing !== []) ? self::FAILURE : self::SUCCESS;
    }
}
