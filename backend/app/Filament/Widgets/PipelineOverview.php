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

    protected ?string $description = 'Where every learner currently sits, from first contact to activated access.';

    protected int|string|array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        // Counted apart, and this is the whole point of importing leads at
        // LEAD rather than as registrations. Rolled together, eighty-five
        // Facebook enquiries read as eighty-five registrations, and the one
        // number the school actually steers on stops being true.
        $leadsDue = Application::whereIn('status', [
            ApplicationStatus::LEAD,
            ApplicationStatus::CONTACTED,
        ])->whereNotNull('next_action_at')->count();

        $overdue = Application::whereIn('status', [
            ApplicationStatus::LEAD,
            ApplicationStatus::CONTACTED,
        ])->where('next_action_at', '<=', now())->count();

        $awaitingDecision = Application::where('status', ApplicationStatus::REGISTRATION_STARTED)->count();
        $awaitingPayment = Application::where('status', ApplicationStatus::AWAITING_PAYMENT)->count();
        $owedCents = Invoice::where('status', InvoiceStatus::DUE)->sum('amount_cents');
        $settledThisMonth = Payment::where('status', PaymentStatus::SETTLED)
            ->where('paid_at', '>=', now()->startOfMonth())
            ->sum('amount_cents');
        $activeEnrolments = Enrolment::where('status', EnrolmentStatus::ACTIVE)->count();
        $profileIncomplete = Application::where('status', ApplicationStatus::PROFILE_INCOMPLETE)->count();
        $unredeemed = AccessToken::where('status', TokenStatus::ISSUED)->count();

        return [
            Stat::make('Leads to call', (string) $leadsDue)
                ->description($overdue > 0
                    ? "{$overdue} overdue — call them today"
                    : ($leadsDue > 0 ? 'All scheduled' : 'Nobody waiting'))
                ->descriptionIcon($overdue > 0 ? 'heroicon-m-phone-arrow-up-right' : 'heroicon-m-check')
                ->color($overdue > 0 ? 'danger' : ($leadsDue > 0 ? 'info' : 'gray')),

            Stat::make('New registrations', (string) $awaitingDecision)
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
            Stat::make('Profile incomplete', (string) $profileIncomplete)
                ->description($profileIncomplete > 0
                    ? 'Paid, with detail still owed'
                    : 'Every paid learner is fully registered')
                ->descriptionIcon('heroicon-m-identification')
                ->color($profileIncomplete > 0 ? 'danger' : 'gray'),

            Stat::make('Tokens unredeemed', (string) $unredeemed)
                ->description($unredeemed > 0 ? 'Issued but never opened in the app' : 'All issued tokens in use')
                ->descriptionIcon('heroicon-m-key')
                ->color($unredeemed > 0 ? 'warning' : 'gray'),
        ];
    }
}
