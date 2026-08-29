<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\ApplicationStatus;
use App\Enums\EnrolmentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\TokenStatus;
use App\Models\AccessToken;
use App\Models\Application;
use App\Models\Enrolment;
use App\Models\Invoice;
use App\Models\Learner;
use App\Models\Payment;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The admissions pipeline in one row, in the order a learner passes through
 * it: applied → owed → paid → enrolled → holding a token.
 *
 * Deliberately not "total rows in each table". A count only earns space on an
 * overview if somebody would act differently depending on the number, so each
 * of these is either work waiting or money outstanding.
 */
class PipelineOverview extends StatsOverviewWidget
{
    protected ?string $heading = 'Pipeline';

    protected ?string $description = 'Where every learner currently sits, from application to activated access.';

    protected int|string|array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $awaitingDecision = Application::where('status', ApplicationStatus::APPLIED)->count();
        $awaitingPayment = Application::where('status', ApplicationStatus::AWAITING_PAYMENT)->count();
        $owedCents = Invoice::where('status', InvoiceStatus::DUE)->sum('amount_cents');
        $settledThisMonth = Payment::where('status', PaymentStatus::SETTLED)
            ->where('paid_at', '>=', now()->startOfMonth())
            ->sum('amount_cents');
        $activeEnrolments = Enrolment::where('status', EnrolmentStatus::ACTIVE)->count();
        $awaitingIdentity = Application::where('status', ApplicationStatus::AWAITING_IDENTITY)->count();
        $unredeemed = AccessToken::where('status', TokenStatus::ISSUED)->count();

        return [
            Stat::make('New applications', (string) $awaitingDecision)
                ->description($awaitingDecision > 0 ? 'Waiting for a decision' : 'Nothing waiting')
                ->descriptionIcon($awaitingDecision > 0 ? 'heroicon-m-inbox-arrow-down' : 'heroicon-m-check')
                ->color($awaitingDecision > 0 ? 'warning' : 'gray'),

            Stat::make('Awaiting payment', (string) $awaitingPayment)
                ->description('R'.number_format($owedCents / 100, 2).' outstanding')
                ->descriptionIcon('heroicon-m-clock')
                ->color($awaitingPayment > 0 ? 'warning' : 'gray'),

            Stat::make('Settled this month', 'R'.number_format($settledThisMonth / 100, 2))
                ->description(Payment::where('status', PaymentStatus::SETTLED)
                    ->where('paid_at', '>=', now()->startOfMonth())->count().' payments received')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Active enrolments', (string) $activeEnrolments)
                ->description(Learner::count().' learners on record')
                ->descriptionIcon('heroicon-m-academic-cap')
                ->color('primary'),

            // Two numbers that mean somebody is stuck. They are the reason
            // this widget exists rather than a row of totals.
            Stat::make('Identity outstanding', (string) $awaitingIdentity)
                ->description($awaitingIdentity > 0
                    ? 'Paid, but no token until ID is verified'
                    : 'No learner is blocked')
                ->descriptionIcon('heroicon-m-identification')
                ->color($awaitingIdentity > 0 ? 'danger' : 'gray'),

            Stat::make('Tokens unredeemed', (string) $unredeemed)
                ->description($unredeemed > 0 ? 'Issued but never opened in the app' : 'All issued tokens in use')
                ->descriptionIcon('heroicon-m-key')
                ->color($unredeemed > 0 ? 'warning' : 'gray'),
        ];
    }
}
