<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ActivationRule;
use App\Enums\BillingModel;
use App\Enums\IntakeStatus;
use App\Enums\OfferingStatus;
use App\Enums\ProgrammeStatus;
use App\Enums\ProgrammeTier;
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
 * This seeder previously carried a catalogue taken from the site navigation
 * and an intake month — "February 2027" — that appears nowhere on the site.
 * Both are gone. The intakes below are the cohort codes the live application
 * form actually offers.
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
        $priced = collect(CatalogueManifest::programmes())->keyBy('code');

        foreach (Programme::all() as $programme) {
            $entry = $priced->get($programme->code);

            if ($entry === null) {
                continue;
            }

            $priceCents = $entry['price_cents'];
            $sellable = $entry['status'] === ProgrammeStatus::OPEN && $priceCents > 0;
            $isCareerModule = $programme->tier === ProgrammeTier::CAREER_MODULE;

            Offering::updateOrCreate(
                ['code' => "{$programme->code}-2026"],
                [
                    'programme_id' => $programme->id,
                    'name' => $programme->name,
                    'description' => $sellable
                        ? $entry['fee_note']
                        : 'No price is published on kcs.edu.za for this programme. Confirm the fee and open this offering before selling it.',
                    // R500 registration then R950 a month is a deposit plus
                    // three instalments; Basic Excel is a single R500 charge.
                    'billing_model' => $isCareerModule
                        ? BillingModel::DEPOSIT_BALANCE
                        : BillingModel::ONE_TIME,
                    'price_cents' => $priceCents,
                    'deposit_cents' => $isCareerModule ? CatalogueManifest::CAREER_MODULE_DEPOSIT_CENTS : null,
                    'instalment_count' => $isCareerModule ? 3 : null,
                    'access_duration_days' => $entry['duration_days'] ?? 90,
                    'activation_rule' => ActivationRule::ON_FIRST_PAYMENT,
                    'status' => $sellable ? OfferingStatus::OPEN : OfferingStatus::DRAFT,
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
        foreach (Programme::all() as $programme) {
            $needsApproval = in_array(
                $programme->tier,
                [ProgrammeTier::PROFESSIONAL, ProgrammeTier::QCTO],
                true,
            );

            ProgrammeRequirement::updateOrCreate(
                ['programme_id' => $programme->id],
                [
                    'rule_type' => $needsApproval ? RequirementRule::MANUAL_APPROVAL : RequirementRule::NONE,
                    'notes' => $needsApproval
                        ? 'Entry rules not published on kcs.edu.za. Confirm with a subject lead before this gates anybody.'
                        : null,
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
    }
}
