<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\InboundWebhook;
use App\Models\Offering;
use App\Services\Enrolment\ApplicationAcceptor;
use App\Services\Enrolment\EnrolmentActivator;
use App\Services\Intake\ApplicationIntake;
use Illuminate\Database\Seeder;

/**
 * A believable February 2027 intake, for looking at the dashboard.
 *
 * Every record is created by the real services — the webhook intake, the
 * acceptor, the activator — so what appears on screen is what the system
 * actually produces, not fixtures shaped to flatter it. Names are invented;
 * no real learner data is used.
 *
 * Never run in production: it is not called by DatabaseSeeder.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $intake = app(ApplicationIntake::class);
        $acceptor = app(ApplicationAcceptor::class);
        $activator = app(EnrolmentActivator::class);

        $people = [
            // [first, last, programme, id number or null, how far they got]
            ['Thabiso', 'Mokoena', 'PPO', '9001015800088', 'token'],
            ['Nomsa', 'Dlamini', 'CRM', '8806145800084', 'token'],
            ['Sipho', 'Ndlovu', 'ICT', '9502285800088', 'paid'],
            ['Lerato', 'Khumalo', 'DMO', null, 'awaiting_identity'],
            ['Kagiso', 'Sithole', 'PROJ', '9911035800084', 'invoiced'],
            ['Palesa', 'Motaung', 'PPO', '9207125800088', 'invoiced'],
            ['Andile', 'Zulu', 'PROC', null, 'applied'],
            ['Zanele', 'Nkosi', 'ENT', null, 'applied'],
            ['Bongani', 'Mahlangu', 'CRM', null, 'applied'],
        ];

        foreach ($people as $index => [$first, $last, $code, $idNumber, $stage]) {
            $payload = $this->submission($index, $first, $last, $code, $idNumber);

            $delivery = InboundWebhook::create([
                'source' => 'fluentform',
                'event_type' => 'application.submitted',
                'external_id' => (string) $payload['submission_id'],
                'signature_valid' => true,
                'payload' => $payload,
                'received_at' => now()->subDays(20 - $index),
            ]);

            $application = $intake->receive($payload, $delivery)['application'];

            if ($stage === 'applied') {
                continue;
            }

            $offering = Offering::where('code', "{$code}-2027-BLOCK")->first();

            if ($offering === null) {
                continue;
            }

            $enrolment = $acceptor->accept($application->fresh(), $offering);

            if ($stage === 'invoiced') {
                continue;
            }

            // Registration fee received — this is what opens access.
            $activator->confirmInvoiceManually(
                invoice: $enrolment->activatingInvoice(),
                providerKey: $index % 2 === 0 ? 'payat_go' : 'eft',
                reference: sprintf('%s-2027%03d', $index % 2 === 0 ? 'PAYAT' : 'FNB', 400 + $index),
            );
        }

        // One learner who has also paid their first monthly instalment, so
        // the money screens show partial settlement rather than all-or-nothing.
        $second = Application::where('status', ApplicationStatus::ENROLLED)
            ->first()?->learner?->enrolments()->first()
            ?->invoices()->where('sequence', 2)->first();

        if ($second !== null) {
            $activator->confirmInvoiceManually($second, 'eft', 'FNB-2027-M1-0001');
        }
    }

    /** @return array<string, mixed> */
    private function submission(int $index, string $first, string $last, string $code, ?string $idNumber): array
    {
        $applicant = [
            'first_name' => $first,
            'last_name' => $last,
            'email' => strtolower("{$first}.{$last}@example.co.za"),
            'phone' => '08'.(1 + $index % 4).' '.(100 + $index).' '.(4000 + $index),
            'highest_qualification' => 'Matric',
            'employment_status' => $index % 3 === 0 ? 'Employed' : 'Unemployed',
            'digital_experience' => $index % 2 === 0 ? 'Beginner' : 'Some experience',
            'referral_source' => ['Facebook', 'Word of mouth', 'Walk-in'][$index % 3],
        ];

        if ($idNumber !== null) {
            $applicant['id_type'] = 'sa_id';
            $applicant['id_number'] = $idNumber;
        }

        return [
            'source' => 'fluentform',
            'form_id' => 8,
            'submission_id' => 100 + $index,
            'submitted_at' => now()->subDays(20 - $index)->toIso8601String(),
            'applicant' => $applicant,
            'programme_code' => $code,
            'intake_label' => 'February 2027',
        ];
    }
}
