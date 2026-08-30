<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Services\Enrolment\EnrolmentActivator;
use App\Services\Payments\PayAtGo\PayAtGoClient;
use App\Services\Payments\Providers\PayAtGoProvider;
use Illuminate\Console\Command;
use Throwable;

/**
 * Ask Pay@ about every reference we are still waiting on.
 *
 * The webhook is a nudge, not a guarantee — it is unsigned, it can be lost,
 * and it cannot be replayed on demand. This sweep is what makes that
 * acceptable: a payment that never generated a callback is found on the next
 * run, and a learner who paid at a till is activated without anyone having to
 * notice. One cron entry, no queue, no daemon.
 *
 *     * /15 * * * *  php /path/to/artisan payat:reconcile
 */
class ReconcilePayAtPayments extends Command
{
    protected $signature = 'payat:reconcile
                            {--limit=200 : How many outstanding references to check in one pass}';

    protected $description = 'Settle Pay@ Go invoices that have been paid, whether or not a callback arrived';

    public function handle(PayAtGoProvider $provider, PayAtGoClient $client, EnrolmentActivator $activator): int
    {
        if (! $client->isConfigured()) {
            $this->warn('Pay@ Go has no credentials configured. Nothing to reconcile.');

            return self::SUCCESS;
        }

        $invoices = Invoice::query()
            ->whereNotNull('payat_account_number')
            ->where('status', InvoiceStatus::DUE->value)
            ->orderBy('payat_requested_at')
            ->limit((int) $this->option('limit'))
            ->get();

        if ($invoices->isEmpty()) {
            $this->info('No outstanding Pay@ references.');

            return self::SUCCESS;
        }

        $settled = 0;
        $failed = 0;

        foreach ($invoices as $invoice) {
            try {
                $result = $provider->reconcile($invoice);
            } catch (Throwable $e) {
                // One unreachable reference must not abandon the rest of the
                // sweep — the next run picks it up either way.
                $this->error("{$invoice->payat_account_number}: {$e->getMessage()}");
                report($e);
                $failed++;

                continue;
            }

            if ($result === null || ! $result->isSettled()) {
                continue;
            }

            $outcome = $activator->settle($result, $invoice);
            $settled++;

            $this->line(sprintf(
                '  %s  %s  R%s  %s',
                $invoice->payat_account_number,
                $invoice->learner?->learner_ref ?? '—',
                number_format($result->amountCents / 100, 2),
                $outcome['already_settled'] ? 'already recorded' : 'settled',
            ));
        }

        $this->info(sprintf(
            'Checked %d reference(s): %d settled, %d unreachable.',
            $invoices->count(),
            $settled,
            $failed,
        ));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
