<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Enums\ApplicationStatus;
use App\Enums\TouchChannel;
use App\Enums\TouchOutcome;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Services\Leads\MetaLeadImporter;
use App\Services\Leads\TouchLog;
use App\Services\Messaging\PaymentMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Throwable;

/**
 * The call queue, as JSON, for the mobile-first page.
 *
 * Filament's table is a spreadsheet: many columns, each claiming its own
 * width, built for a desk. On a phone screen that shape is the problem
 * itself — the row a caller needs still ran wider than the screen after
 * hiding columns and shrinking buttons, because a table is not a list. This
 * is the same data and the same TouchLog/MetaLeadImporter underneath, served
 * to a page built for one thing: standing in a queue, working down a list of
 * cards, tapping WhatsApp or Call, logging what happened, moving on.
 *
 * Authenticated the same way the dashboard is — the `web` session guard — so
 * a staff member's existing login works here with nothing new to remember.
 */
class CallQueueController extends Controller
{
    public function shell(): View
    {
        return view('staff.calls', [
            'staffName' => auth()->user()?->name,
            // Built from route(), not hardcoded: the panel mounts at /admin
            // locally but at the directory root in production (the front
            // controller already sits inside public_html/admin), so a literal
            // "/admin/applications" would be wrong in exactly one of the two
            // places this runs.
            'applicationsUrl' => route('filament.admin.resources.applications.index'),
            'dashboardUrl' => route('filament.admin.pages.dashboard'),
        ]);
    }

    /** The queue, oldest-waiting first — same ordering as the dashboard widget. */
    public function index(): JsonResponse
    {
        $leads = Application::query()
            ->with(['learner', 'leadTouches' => fn ($q) => $q->latest('occurred_at')->limit(1)])
            ->whereIn('status', [ApplicationStatus::LEAD, ApplicationStatus::CONTACTED])
            ->whereNotNull('next_action_at')
            ->orderBy('next_action_at')
            ->limit(200)
            ->get();

        return response()->json([
            'leads' => $leads->map(fn (Application $a) => $this->present($a))->values(),
        ]);
    }

    public function logCall(Request $request, Application $application): JsonResponse
    {
        $data = $request->validate([
            'channel' => ['required', 'string'],
            'outcome' => ['required', 'string'],
            'note' => ['nullable', 'string', 'max:2000'],
            'next_action_at' => ['nullable', 'date'],
        ]);

        app(TouchLog::class)->record(
            application: $application,
            channel: TouchChannel::from($data['channel']),
            outcome: TouchOutcome::from($data['outcome']),
            note: $data['note'] ?? null,
            by: $request->user(),
            nextActionAt: isset($data['next_action_at']) ? Carbon::parse($data['next_action_at']) : null,
        );

        return response()->json(['lead' => $this->present($application->fresh(['learner', 'leadTouches']))]);
    }

    /** A tap on WhatsApp counts as a touch too — same rule as the dashboard widget. */
    public function whatsapp(Request $request, Application $application): JsonResponse
    {
        $link = app(PaymentMessage::class)->leadIntroWhatsAppLink($application->learner, $request->user()?->name);

        if ($link === null) {
            return response()->json(['message' => 'No WhatsApp number on file for this person.'], 422);
        }

        app(TouchLog::class)->record(
            application: $application,
            channel: TouchChannel::WHATSAPP,
            outcome: TouchOutcome::SENT_INFO,
            note: 'Opened WhatsApp from the mobile queue.',
            by: $request->user(),
        );

        return response()->json(['url' => $link, 'lead' => $this->present($application->fresh(['learner', 'leadTouches']))]);
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file'],
            'campaign' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            $result = app(MetaLeadImporter::class)->importFile(
                path: $request->file('file')->getRealPath(),
                campaign: $request->string('campaign')->value() ?: null,
            );
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($result);
    }

    /** @return array<string, mixed> */
    private function present(Application $application): array
    {
        $learner = $application->learner;
        $lastTouch = $application->leadTouches->first();
        $whatsappNumber = app(PaymentMessage::class)->leadIntroWhatsAppLink($learner) !== null;

        return [
            'id' => $application->id,
            'name' => trim("{$learner->first_name} {$learner->last_name}") ?: $learner->learner_ref,
            'phone' => $learner->phone,
            'email' => $learner->email,
            'campaign' => $application->campaign,
            'source' => $application->source->label(),
            'touch_count' => $application->touch_count,
            'last_outcome' => $lastTouch?->outcome->value,
            'last_outcome_label' => $lastTouch?->outcome->label(),
            'last_note' => $lastTouch?->note,
            'next_action_at' => $application->next_action_at?->toIso8601String(),
            'overdue' => $application->next_action_at?->isPast() ?? false,
            'urgent' => $application->next_action_at?->lt(now()->subDays(3)) ?? false,
            'can_whatsapp' => $whatsappNumber,
            'can_register' => $lastTouch?->outcome === TouchOutcome::WILL_REGISTER,
        ];
    }
}
