<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Leads\MetaLeadImporter;
use Illuminate\Console\Command;
use Throwable;

/**
 * Bring a Meta Leads Center export onto the ladder.
 *
 *   php artisan leads:import storage/app/leads-august.csv --campaign="Aug 2026"
 *
 * Safe to run twice: leads are matched on Meta's own lead id, and anyone
 * already on the ladder is left where they are.
 */
class ImportMetaLeads extends Command
{
    protected $signature = 'leads:import
        {file : Path to the CSV downloaded from the Leads Center}
        {--campaign= : Override the campaign name rather than reading it from the file}
        {--programme= : Programme id to file these leads under}';

    protected $description = 'Import a Facebook or Instagram lead export as leads';

    public function handle(MetaLeadImporter $importer): int
    {
        $file = (string) $this->argument('file');

        try {
            $result = $importer->importFile(
                path: $file,
                campaign: $this->option('campaign') ?: null,
                programmeId: $this->option('programme') ? (int) $this->option('programme') : null,
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->line("  <options=bold>{$result['imported']}</> new leads, ready to call.");

        if ($result['duplicates'] > 0) {
            $this->line("  {$result['duplicates']} already on the ladder — left alone.");
        }

        if ($result['campaign'] !== null) {
            $this->line("  Filed under campaign: {$result['campaign']}");
        }

        foreach ($result['skipped'] as $reason) {
            $this->warn("  Skipped — {$reason}");
        }

        $this->newLine();
        $this->line('  They are all due now. Open the dashboard and start calling.');
        $this->newLine();

        return self::SUCCESS;
    }
}
