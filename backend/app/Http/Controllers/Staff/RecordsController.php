<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Enums\ApplicationStatus;
use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Invoice;
use App\Models\Learner;
use App\Services\Messaging\PaymentMessage;
use App\Services\Registration\ProfileCompleteness;
use Illuminate\View\View;

/**
 * Everything that already has a name attached to it — as opposed to /calls,
 * which is everyone who does not yet. One controller, three real queries
 * behind a segmented tab, because they are three views of the same ladder
 * rather than three separate resources.
 */
class RecordsController extends Controller
{
    public function index(ProfileCompleteness $completeness, PaymentMessage $messages): View
    {
        $tab = request()->string('tab')->value();
        $tab = in_array($tab, ['registrations', 'learners', 'invoices'], true) ? $tab : 'registrations';

        return view('staff.records', [
            'tab' => $tab,
            'registrations' => $tab === 'registrations' ? $this->registrations($completeness, $messages) : null,
            'learners' => $tab === 'learners' ? $this->learners($completeness, $messages) : null,
            'invoices' => $tab === 'invoices' ? $this->invoices() : null,
        ]);
    }

    public function learner(Learner $learner, ProfileCompleteness $completeness, PaymentMessage $messages): View
    {
        $learner->load(['enrolments.programme', 'enrolments.intake', 'invoices' => fn ($q) => $q->latest('due_on')]);
        $dueInvoice = $learner->invoices->firstWhere('status', InvoiceStatus::DUE);
        $settled = $learner->invoices->where('status', InvoiceStatus::PAID)->count();

        return view('staff.learner', [
            'learner' => $learner,
            'percent' => $completeness->percent($learner),
            'missing' => $completeness->missing($learner),
            'enrolment' => $learner->enrolments->sortByDesc('id')->first(),
            'dueInvoice' => $dueInvoice,
            'settledCount' => $settled,
            'totalInvoices' => $learner->invoices->count(),
            'paymentLink' => $dueInvoice ? $messages->whatsAppLinkFor($dueInvoice) : null,
            'profileLink' => $messages->profileWhatsAppLink($learner),
        ]);
    }

    private function registrations(ProfileCompleteness $completeness, PaymentMessage $messages)
    {
        return Application::query()
            ->with('learner', 'programme')
            ->whereIn('status', [
                ApplicationStatus::REGISTRATION_STARTED,
                ApplicationStatus::AWAITING_PAYMENT,
                ApplicationStatus::PROFILE_INCOMPLETE,
            ])
            ->latest('applied_at')
            ->limit(100)
            ->get()
            ->map(fn (Application $a) => [
                'application' => $a,
                'percent' => $completeness->percent($a->learner),
                'profile_link' => $messages->profileWhatsAppLink($a->learner),
            ]);
    }

    private function learners(ProfileCompleteness $completeness, PaymentMessage $messages)
    {
        return Learner::query()
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(fn (Learner $l) => [
                'learner' => $l,
                'percent' => $completeness->percent($l),
                'profile_link' => $messages->profileWhatsAppLink($l),
            ]);
    }

    private function invoices()
    {
        return Invoice::query()
            ->with('learner', 'enrolment.programme')
            ->latest('due_on')
            ->limit(100)
            ->get();
    }
}
