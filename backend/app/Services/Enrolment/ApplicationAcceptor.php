<?php

declare(strict_types=1);

namespace App\Services\Enrolment;

use App\Enums\ApplicationStatus;
use App\Enums\EnrolmentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\OfferingStatus;
use App\Models\Application;
use App\Models\Enrolment;
use App\Models\Invoice;
use App\Models\Offering;
use App\Models\User;
use App\Services\Billing\FeeSchedule;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Accepts an application into a pending enrolment and raises what is owed.
 *
 * The invoices come from the offering's own commercial configuration, not
 * from whoever is accepting the application. That is the correction: a fixed
 * three-month block produces one invoice because its billing model says so,
 * and no registrar can accidentally turn it into three.
 */
class ApplicationAcceptor
{
    public function __construct(private readonly FeeSchedule $fees) {}

    public function accept(Application $application, Offering $offering, ?User $actor = null): Enrolment
    {
        if ($application->programme_id === null) {
            throw new DomainException(
                "Application {$application->id} has no programme — assign one before accepting it.",
            );
        }

        if ($offering->programme_id !== $application->programme_id) {
            throw new DomainException(
                "Offering [{$offering->code}] does not belong to the programme this application is for.",
            );
        }

        // A draft offering is one whose price has not been confirmed. Selling
        // under it is exactly the mistake this whole correction is about, so
        // it is refused rather than trusted.
        if ($offering->status !== OfferingStatus::OPEN) {
            throw new DomainException(
                "Offering [{$offering->code}] is {$offering->status->value}, not open. ".
                'Confirm its price and open it before accepting applications against it.',
            );
        }

        $lines = $this->fees->linesFor($offering);
        $this->fees->assertConsistent($offering, $lines);

        return DB::transaction(function () use ($application, $offering, $lines, $actor) {
            $enrolment = Enrolment::firstOrCreate(
                [
                    'learner_id' => $application->learner_id,
                    'programme_id' => $application->programme_id,
                    'intake_id' => $application->intake_id,
                ],
                [
                    'application_id' => $application->id,
                    'offering_id' => $offering->id,
                    'status' => EnrolmentStatus::PENDING,
                    'starts_on' => $application->intake?->starts_on,
                    'ends_on' => $application->intake?->ends_on,
                ],
            );

            foreach ($lines as $line) {
                Invoice::firstOrCreate(
                    ['enrolment_id' => $enrolment->id, 'sequence' => $line->sequence],
                    [
                        'learner_id' => $application->learner_id,
                        'description' => $line->description,
                        'amount_cents' => $line->amountCents,
                        'currency' => $offering->currency,
                        'due_on' => now()->addDays($line->dueInDays)->toDateString(),
                        'activates_enrolment' => $line->activatesEnrolment,
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
