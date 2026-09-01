<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\EntitlementState;
use App\Models\Entitlement;
use App\Models\Learner;
use App\Models\Programme;
use App\Services\Identity\LabPin;
use Illuminate\Console\Command;

/**
 * Three learners who share a computer, for trying the lab rotation.
 *
 * Their references start DEMO- rather than NAL-, so they can never be mistaken
 * for a real learner and are trivial to find and delete. Re-running this
 * reuses the same three and issues fresh PINs.
 */
class MakeDemoLearners extends Command
{
    protected $signature = 'lab:demo-learners {--programme=PPO}';

    protected $description = 'Create three demo learners with lab PINs, for testing the workspace';

    public function handle(LabPin $pins): int
    {
        if (app()->isProduction() && ! $this->confirm('This is production. Really create demo learners?', false)) {
            $this->warn('Nothing created.');

            return self::FAILURE;
        }

        $programme = Programme::where('code', $this->option('programme'))->first();

        if ($programme === null) {
            $this->error("No programme {$this->option('programme')}. Run `php artisan db:seed --class=ProgrammeSeeder` first.");

            return self::FAILURE;
        }

        $rows = [];

        foreach ([
            ['Naledi', 'Khumalo'],
            ['Sipho', 'Dube'],
            ['Lerato', 'Nkosi'],
        ] as $index => [$first, $last]) {
            $reference = 'DEMO-2026-'.str_pad((string) ($index + 1), 5, '0', STR_PAD_LEFT);

            $learner = Learner::firstOrCreate(
                ['learner_ref' => $reference],
                [
                    'first_registered_year' => 2026,
                    'first_name' => $first,
                    'last_name' => $last,
                    'email' => strtolower($first).'.demo@example.co.za',
                ],
            );

            Entitlement::firstOrCreate(
                ['learner_id' => $learner->id, 'programme_id' => $programme->id],
                [
                    'state' => EntitlementState::ACTIVE,
                    'unlocked_at' => now(),
                    'expires_at' => now()->addDays(90),
                ],
            );

            $rows[] = [$reference, "{$first} {$last}", $pins->issue($learner)];
        }

        $this->newLine();
        $this->table(['Student number', 'Name', 'PIN'], $rows);
        $this->line('  Sign in at '.url('/workspace').'  —  PINs are hashed, so this is the only time they are shown.');
        $this->newLine();

        return self::SUCCESS;
    }
}
