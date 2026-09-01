<?php

declare(strict_types=1);

namespace App\Services\Intake;

use App\Enums\ApplicationSource;
use App\Enums\ApplicationStatus;
use App\Enums\FundingSource;
use App\Enums\FundingStatus;
use App\Models\Application;
use App\Models\InboundWebhook;
use App\Models\Intake;
use App\Models\Programme;
use App\Services\Identity\LearnerRegistry;
use App\Services\Messaging\LearnerMailer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Turns a website form submission into a learner and an application.
 *
 * The existing Student Application Form on kcs.edu.za stays exactly as it is —
 * 35 real submissions and counting — and this is what receives it. Nothing
 * about the public journey changes; the backend simply becomes the place the
 * answers land.
 */
class ApplicationIntake
{
    public function __construct(
        private readonly LearnerRegistry $learners,
        private readonly LearnerMailer $mailer,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{application: Application, created: bool}
     */
    public function receive(array $payload, InboundWebhook $delivery): array
    {
        $result = DB::transaction(function () use ($payload, $delivery) {
            $source = ApplicationSource::FLUENTFORM;
            $formId = isset($payload['form_id']) ? (int) $payload['form_id'] : null;
            $reference = isset($payload['submission_id']) ? (string) $payload['submission_id'] : null;

            // Fluent Forms retries a failed delivery, so the same submission
            // can arrive more than once. The unique index makes the second
            // one a lookup rather than a duplicate learner.
            $existing = Application::where('source', $source)
                ->where('source_form_id', $formId)
                ->where('source_reference', $reference)
                ->first();

            if ($existing !== null) {
                $delivery->update([
                    'related_type' => Application::class,
                    'related_id' => $existing->id,
                    'processed_at' => now(),
                ]);

                return ['application' => $existing, 'created' => false];
            }

            $applicant = (array) ($payload['applicant'] ?? []);
            $learner = $this->learners->resolve($applicant);

            $programme = $this->resolveProgramme($payload);
            $funding = $this->resolveFunding($payload['funding_source'] ?? null);
            $intake = $this->resolveIntake($programme, $payload['intake_label'] ?? null);

            $application = Application::create([
                'learner_id' => $learner->id,
                'programme_id' => $programme?->id,
                'intake_id' => $intake?->id,
                'status' => ApplicationStatus::REGISTRATION_STARTED,
                'source' => $source,
                'source_form_id' => $formId,
                'source_reference' => $reference,
                'campaign' => $this->nullableString($payload['campaign'] ?? null),
                'funding_source' => $funding,
                'funding_status' => $funding === null
                    ? null
                    : ($funding->needsFundingWork() ? FundingStatus::PENDING : FundingStatus::NOT_REQUIRED),
                'payload' => $payload,
                'applied_at' => isset($payload['submitted_at'])
                    ? Carbon::parse($payload['submitted_at'])
                    : now(),
            ]);

            $delivery->update([
                'related_type' => Application::class,
                'related_id' => $application->id,
                'processed_at' => now(),
            ]);

            return ['application' => $application, 'created' => true];
        });

        // Outside the transaction on purpose. Sending inside would hold a
        // database lock open for the length of an SMTP conversation, and a
        // rollback afterwards could not unsend the message. The mailer
        // swallows its own failures, so a registration never depends on email
        // working — the money path must not break because Google is slow.
        if ($result['created']) {
            $this->mailer->registrationReceived($result['application']);
        }

        return $result;
    }

    /**
     * "How will your studies be paid for?" on the registration form, matched
     * against the enum by its own label.
     *
     * An answer we do not recognise returns null rather than guessing. Self
     * funding raises no funding matter; everything else lands as pending so it
     * shows up in the queue without interrupting the registration.
     */
    private function resolveFunding(mixed $raw): ?FundingSource
    {
        $value = trim((string) $raw);

        if ($value === '') {
            return null;
        }

        $direct = FundingSource::tryFrom($value);

        if ($direct !== null) {
            return $direct;
        }

        foreach (FundingSource::cases() as $case) {
            if (strcasecmp($case->label(), $value) === 0 || strcasecmp($case->shortLabel(), $value) === 0) {
                return $case;
            }
        }

        return null;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * A code we recognise wins; otherwise the form's own option text is looked
     * up in the configured map. An unmapped choice returns null so the
     * application still lands and a registrar assigns it — losing a real
     * applicant to a spelling difference would be the worse failure.
     *
     * @param  array<string, mixed>  $payload
     */
    private function resolveProgramme(array $payload): ?Programme
    {
        $code = trim((string) ($payload['programme_code'] ?? ''));

        if ($code !== '') {
            $programme = Programme::where('code', strtoupper($code))->first();

            if ($programme !== null) {
                return $programme;
            }

            $mapped = config('webhooks.fluentform.programme_map')[$code] ?? null;

            if ($mapped !== null) {
                return Programme::where('code', $mapped)->first();
            }

            // Last resort: the option text may simply be the programme name.
            return Programme::where('name', $code)
                ->orWhere('slug', Str::slug($code))
                ->first();
        }

        return null;
    }

    private function resolveIntake(?Programme $programme, mixed $label): ?Intake
    {
        if ($programme === null || ! is_string($label) || trim($label) === '') {
            return null;
        }

        return Intake::where('programme_id', $programme->id)
            ->where('label', trim($label))
            ->first();
    }
}
