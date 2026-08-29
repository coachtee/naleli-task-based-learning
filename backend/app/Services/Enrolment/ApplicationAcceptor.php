<?php

declare(strict_types=1);

namespace App\Services\Enrolment;

use App\Enums\ApplicationStatus;
use App\Enums\EnrolmentStatus;
use App\Enums\InvoiceStatus;
use App\Models\Application;
use App\Models\Enrolment;
use App\Models\Invoice;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Accepts an application into a pending enrolment and raises what is owed.
 *
 * Invoices are supplied by the caller rather than generated from a fee rule,
 * because the commercial model is deliberately still open: R500 plus R950 a
 * month, a single programme fee and a three-month block are all just different
 * row counts here. Exactly one invoice carries `activates_enrolment`, and that
 * is the only thing the activation rule cares about — so confirming the model
 * later changes which rows a registrar raises, not this code.
 */
class ApplicationAcceptor
{
    /**
     * @param  array<int, array{description: string, amount_cents: int, due_on?: string|null, activates?: bool}>  $invoices
     */
    public function accept(Application $application, array $invoices, ?User $actor = null): Enrolment
    {
        if ($application->programme_id === null) {
            throw new DomainException(
                "Application {$application->id} has no programme — assign one before accepting it.",
            );
        }

        if (count(array_filter($invoices, fn (array $i): bool => $i['activates'] ?? false)) !== 1) {
            throw new DomainException(
                'Exactly one invoice must be marked as activating the enrolment.',
            );
        }

        return DB::transaction(function () use ($application, $invoices, $actor) {
            $enrolment = Enrolment::firstOrCreate(
                [
                    'learner_id' => $application->learner_id,
                    'programme_id' => $application->programme_id,
                    'intake_id' => $application->intake_id,
                ],
                [
                    'application_id' => $application->id,
                    'status' => EnrolmentStatus::PENDING,
                    'starts_on' => $application->intake?->starts_on,
                    'ends_on' => $application->intake?->ends_on,
                ],
            );

            foreach (array_values($invoices) as $index => $line) {
                Invoice::firstOrCreate(
                    [
                        'enrolment_id' => $enrolment->id,
                        'sequence' => $index + 1,
                    ],
                    [
                        'learner_id' => $application->learner_id,
                        'description' => $line['description'],
                        'amount_cents' => $line['amount_cents'],
                        'due_on' => $line['due_on'] ?? null,
                        'activates_enrolment' => $line['activates'] ?? false,
                        'status' => InvoiceStatus::DUE,
                        'created_by' => $actor?->id,
                    ],
                );
            }

            $application->update([
                'status' => ApplicationStatus::AWAITING_PAYMENT,
                'decided_at' => now(),
                'decided_by' => $actor?->id,
            ]);

            return $enrolment->refresh();
        });
    }
}
