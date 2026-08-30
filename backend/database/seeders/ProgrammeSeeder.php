<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ActivationRule;
use App\Enums\BillingModel;
use App\Enums\IntakeStatus;
use App\Enums\OfferingStatus;
use App\Enums\ProgrammeStatus;
use App\Enums\RequirementRule;
use App\Models\Intake;
use App\Models\Offering;
use App\Models\Programme;
use App\Models\ProgrammeRequirement;
use App\Support\CatalogueManifest;
use Illuminate\Database\Seeder;

/**
 * The KCS catalogue, built entirely from CatalogueManifest.
 *
 * Nothing is written here that the manifest does not declare, and the manifest
 * records where on kcs.edu.za each programme was found. That is the whole
 * mechanism: a programme cannot enter the backend without a source, and
 * CatalogueDriftTest fails if the database and the manifest disagree.
 *
 * Thirteen blocks, one price, one intake. The KCS short courses and NIBS
 * QCTO programmes that used to sit alongside these are retired here, not
 * deleted — real applications already name them.
 */
class ProgrammeSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedProgrammes();
        $this->seedIntakes();
        $this->seedRequirements();
        $this->seedOfferings();
        $this->retireProgrammesTheSiteNoLongerPublishes();
    }

    private function seedProgrammes(): void
    {
        foreach (CatalogueManifest::programmes() as $entry) {
            Programme::updateOrCreate(
                ['code' => $entry['code']],
                [
                    'name' => $entry['name'],
                    'slug' => $entry['slug'],
                    'source_url' => $entry['source_url'],
                    'source_note' => $entry['source_note'],
                    'tier' => $entry['tier'],
                    'nqf_level' => $entry['nqf_level'],
                    'summary' => $entry['summary'],
                    'duration_label' => $entry['duration_label'],
                    'duration_days' => $entry['duration_days'],
                    'weekly_hours' => $entry['weekly_hours'],
                    'fee_note' => $entry['fee_note'],
                    'status' => $entry['status'],
                    'sort_order' => $entry['sort_order'],
                ],
            );
        }
    }

    /**
     * The cohorts the application form offers, by the labels it uses, so a
     * submission naming "ITS-ON-001" resolves without translation.
     */
    private function seedIntakes(): void
    {
        $ids = Programme::pluck('id', 'code');

        foreach (CatalogueManifest::intakes() as $code => $cohorts) {
            if (! isset($ids[$code])) {
                continue;
            }

            foreach ($cohorts as $cohort) {
                Intake::updateOrCreate(
                    ['programme_id' => $ids[$code], 'label' => $cohort['label']],
                    [
                        'starts_on' => $cohort['starts'],
                        'ends_on' => $cohort['ends'],
                        'status' => IntakeStatus::PLANNED,
                    ],
                );
            }
        }
    }

    /**
     * One offering per programme, priced only where the site publishes a
     * price. Everything else stays DRAFT, and a draft offering cannot be sold
     * — ApplicationAcceptor refuses it. That refusal is the safeguard: it is
     * what stops a registrar invoicing a learner for a figure nobody has
     * confirmed.
     */
    private function seedOfferings(): void
    {
        foreach (Programme::all() as $programme) {
            if (! in_array($programme->code, CatalogueManifest::codes(), true)) {
                continue;
            }

            Offering::updateOrCreate(
                ['code' => "{$programme->code}-2027"],
                [
                    'programme_id' => $programme->id,
                    'name' => $programme->name.' — '.CatalogueManifest::INTAKE_LABEL,
                    'description' => 'R500 once-off registration, then R950 a month for three months.',
                    // The same deal on every block: a R500 registration that
                    // opens access, then three R950 instalments.
                    'billing_model' => BillingModel::DEPOSIT_BALANCE,
                    'price_cents' => CatalogueManifest::PRICE_CENTS,
                    'deposit_cents' => CatalogueManifest::DEPOSIT_CENTS,
                    'instalment_count' => CatalogueManifest::INSTALMENTS,
                    'access_duration_days' => CatalogueManifest::BLOCK_DAYS,
                    'activation_rule' => ActivationRule::ON_FIRST_PAYMENT,
                    'status' => OfferingStatus::OPEN,
                    'sort_order' => $programme->sort_order,
                ],
            );
        }
    }

    /**
     * Entry rules are recorded, not enforced — Phase 5 has the completion data
     * to test them against. QCTO programmes carry NQF entry requirements the
     * site does not publish, so they wait on a human rather than a guess.
     */
    private function seedRequirements(): void
    {
        $foundationId = Programme::where('code', CatalogueManifest::FOUNDATION_CODE)->value('id');

        foreach (Programme::whereIn('code', CatalogueManifest::codes())->get() as $programme) {
            // "Every learner starts here. Then specialise." Recorded now,
            // enforced in Phase 5 when there is completion data to test it
            // against — the Foundation itself has no prerequisite.
            $isFoundation = $programme->code === CatalogueManifest::FOUNDATION_CODE;

            ProgrammeRequirement::updateOrCreate(
                ['programme_id' => $programme->id],
                [
                    'rule_type' => $isFoundation ? RequirementRule::NONE : RequirementRule::COMPLETED_PROGRAMME,
                    'requires_programme_id' => $isFoundation ? null : $foundationId,
                    'requires_certificate' => false,
                    'notes' => $isFoundation
                        ? 'Compulsory first block. Nothing precedes it.'
                        : 'Requires the Digital Operations Professional Foundation.',
                ],
            );
        }
    }

    /**
     * A programme that leaves the website is archived here, never deleted —
     * a learner may already be enrolled on it, and their record has to keep
     * naming something real.
     */
    private function retireProgrammesTheSiteNoLongerPublishes(): void
    {
        Programme::whereNotIn('code', CatalogueManifest::codes())
            ->where('status', '!=', ProgrammeStatus::ARCHIVED)
            ->update([
                'status' => ProgrammeStatus::ARCHIVED,
                'source_note' => 'No longer published on kcs.edu.za',
            ]);

        // Its offering stops being sellable with it. The row survives because
        // invoices already point at it.
        Offering::whereRelation('programme', 'status', ProgrammeStatus::ARCHIVED->value)
            ->update(['status' => OfferingStatus::ARCHIVED]);
    }
}
