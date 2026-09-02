<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Enums\ApplicationStatus;
use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Invoice;
use App\Models\LeadTouch;
use Illuminate\View\View;

/**
 * The first thing a salesperson sees: what today actually is, not a menu.
 *
 * Every number here is a real query, not a target — there is no configured
 * daily call goal anywhere in the system, so this shows what was done rather
 * than inventing a quota to measure it against.
 */
class DashboardController extends Controller
{
    public function index(): View
    {
        $queue = Application::query()
            ->whereIn('status', [ApplicationStatus::LEAD, ApplicationStatus::CONTACTED])
            ->whereNotNull('next_action_at');

        $leadsToCall = (clone $queue)->count();
        $overdue = (clone $queue)->where('next_action_at', '<', now())->count();

        $preview = Application::query()
            ->with(['learner', 'leadTouches' => fn ($q) => $q->latest('occurred_at')->limit(1)])
            ->whereIn('status', [ApplicationStatus::LEAD, ApplicationStatus::CONTACTED])
            ->whereNotNull('next_action_at')
            ->orderBy('next_action_at')
            ->limit(3)
            ->get();

        return view('staff.dashboard', [
            'leadsToCall' => $leadsToCall,
            'overdue' => $overdue,
            'newRegistrations' => Application::where('status', ApplicationStatus::REGISTRATION_STARTED)->count(),
            'awaitingPayment' => Application::where('status', ApplicationStatus::AWAITING_PAYMENT)->count(),
            'outstandingCents' => Invoice::where('status', InvoiceStatus::DUE)->sum('amount_cents'),
            'settledCents' => Invoice::where('status', InvoiceStatus::PAID)
                ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('amount_cents'),
            'settledCount' => Invoice::where('status', InvoiceStatus::PAID)
                ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->count(),
            'touchesToday' => LeadTouch::where('user_id', auth()->id())->whereDate('occurred_at', today())->count(),
            'preview' => $preview,
        ]);
    }
}
