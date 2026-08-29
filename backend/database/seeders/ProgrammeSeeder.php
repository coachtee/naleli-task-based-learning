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
use Illuminate\Database\Seeder;

/**
 * The live KCS catalogue as it stands on kcs.edu.za: seven Career Modules,
 * six Professional Specialisations, the foundation programme and one short
 * course.
 *
 * Prices are display text only. Structured pricing arrives with the confirmed
 * commercial model — encoding one now would invent a decision that is
 * deliberately still open (see the backend proposal, section 3).
 */
class ProgrammeSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->programmes() as $attributes) {
            Programme::updateOrCreate(['code' => $attributes['code']], $attributes);
        }

        $this->seedIntakes();
        $this->seedRequirements();
        $this->seedOfferings();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function programmes(): array
    {
        return [
            [
                'code' => 'PPO',
                'name' => 'People & Payroll Operations',
                'slug' => 'people-payroll-operations',
                'tier' => ProgrammeTier::CAREER_MODULE,
                'summary' => 'HR administration, employee records, payroll and HR systems.',
                'duration_label' => '3-month block',
                'duration_days' => 90,
                'weekly_hours' => '8-10',
                'fee_note' => 'R500 once-off, then R950 per month',
                'content_code' => null,
                'status' => ProgrammeStatus::OPEN,
                'sort_order' => 10,
            ],
            [
                'code' => 'CRM',
                'name' => 'Customer & CRM Operations',
                'slug' => 'customer-crm-operations',
                'tier' => ProgrammeTier::CAREER_MODULE,
                'summary' => 'Customer service, CRM, sales pipelines and customer administration.',
                'duration_label' => '3-month block',
                'duration_days' => 90,
                'weekly_hours' => '8-10',
                'fee_note' => 'R500 once-off, then R950 per month',
                'content_code' => null,
                'status' => ProgrammeStatus::OPEN,
                'sort_order' => 20,
            ],
            [
                'code' => 'PROC',
                'name' => 'Procurement & Tender Operations',
                'slug' => 'procurement-tender-operations',
                'tier' => ProgrammeTier::CAREER_MODULE,
                'summary' => 'Suppliers, quotations, procurement and tender administration.',
                'duration_label' => '3-month block',
                'duration_days' => 90,
                'weekly_hours' => '8-10',
                'fee_note' => 'R500 once-off, then R950 per month',
                'content_code' => null,
                'status' => ProgrammeStatus::OPEN,
                'sort_order' => 30,
            ],
            [
                'code' => 'ENT',
                'name' => 'Entrepreneurship & Business Operations',
                'slug' => 'entrepreneurship-business-operations',
                'tier' => ProgrammeTier::CAREER_MODULE,
                'summary' => 'SME and business administration, digital business operations.',
                'duration_label' => '3-month block',
                'duration_days' => 90,
                'weekly_hours' => '8-10',
                'fee_note' => 'R500 once-off, then R950 per month',
                'content_code' => null,
                'status' => ProgrammeStatus::OPEN,
                'sort_order' => 40,
            ],
            [
                'code' => 'DMO',
                'name' => 'Digital Marketing Operations',
                'slug' => 'digital-marketing-operations',
                'tier' => ProgrammeTier::CAREER_MODULE,
                'summary' => 'Content, social media, campaigns and digital analytics.',
                'duration_label' => '3-month block',
                'duration_days' => 90,
                'weekly_hours' => '8-10',
                'fee_note' => 'R500 once-off, then R950 per month',
                'content_code' => null,
                'status' => ProgrammeStatus::OPEN,
                'sort_order' => 50,
            ],
            [
                'code' => 'PROJ',
                'name' => 'Project Operations',
                'slug' => 'project-operations',
                'tier' => ProgrammeTier::CAREER_MODULE,
                'summary' => 'Project administration, planning, coordination and reporting.',
                'duration_label' => '3-month block',
                'duration_days' => 90,
                'weekly_hours' => '8-10',
                'fee_note' => 'R500 once-off, then R950 per month',
                'content_code' => null,
                'status' => ProgrammeStatus::OPEN,
                'sort_order' => 60,
            ],
            [
                'code' => 'ICT',
                'name' => 'ICT Systems Administration',
                'slug' => 'ict-systems-administration',
                'tier' => ProgrammeTier::CAREER_MODULE,
                'summary' => 'IT support, users, systems, networks, cloud and troubleshooting.',
                'duration_label' => '3-month block',
                'duration_days' => 90,
                'weekly_hours' => '8-10',
                'fee_note' => 'R500 once-off, then R950 per month',
                'content_code' => null,
                'status' => ProgrammeStatus::OPEN,
                'sort_order' => 70,
            ],
            [
                'code' => 'BIA',
                'name' => 'Business Intelligence & Analytics Professional',
                'slug' => 'business-intelligence-analytics-professional',
                'tier' => ProgrammeTier::PROFESSIONAL,
                'summary' => 'Data, reporting, dashboards and decision support.',
                'duration_label' => '3-month block',
                'duration_days' => 90,
                'weekly_hours' => null,
                'fee_note' => 'Fees and dates on enquiry',
                'content_code' => null,
                'status' => ProgrammeStatus::OPEN,
                'sort_order' => 80,
            ],
            [
                'code' => 'CHG',
                'name' => 'Change Management Professional',
                'slug' => 'change-management-professional',
                'tier' => ProgrammeTier::PROFESSIONAL,
                'summary' => 'Technology and process adoption, organisational change.',
                'duration_label' => '3-month block',
                'duration_days' => 90,
                'weekly_hours' => null,
                'fee_note' => 'Fees and dates on enquiry',
                'content_code' => null,
                'status' => ProgrammeStatus::OPEN,
                'sort_order' => 90,
            ],
            [
                'code' => 'ERP',
                'name' => 'ERP Systems Administration Professional',
                'slug' => 'erp-systems-administration-professional',
                'tier' => ProgrammeTier::PROFESSIONAL,
                'summary' => 'ERP administration and business systems.',
                'duration_label' => '3-month block',
                'duration_days' => 90,
                'weekly_hours' => null,
                'fee_note' => 'Fees and dates on enquiry',
                'content_code' => null,
                'status' => ProgrammeStatus::OPEN,
                'sort_order' => 100,
            ],
            [
                'code' => 'MIS',
                'name' => 'Management Information Systems Professional',
                'slug' => 'management-information-systems-professional',
                'tier' => ProgrammeTier::PROFESSIONAL,
                'summary' => 'Business information systems.',
                'duration_label' => '3-month block',
                'duration_days' => 90,
                'weekly_hours' => null,
                'fee_note' => 'Fees and dates on enquiry',
                'content_code' => null,
                'status' => ProgrammeStatus::OPEN,
                'sort_order' => 110,
            ],
            [
                'code' => 'OPS',
                'name' => 'Operations Management Professional',
                'slug' => 'operations-management-professional',
                'tier' => ProgrammeTier::PROFESSIONAL,
                'summary' => 'Processes, workflows and operational improvement.',
                'duration_label' => '3-month block',
                'duration_days' => 90,
                'weekly_hours' => null,
                'fee_note' => 'Fees and dates on enquiry',
                'content_code' => null,
                'status' => ProgrammeStatus::OPEN,
                'sort_order' => 120,
            ],
            [
                'code' => 'DOP',
                'name' => 'Digital Operations Professional',
                'slug' => 'digital-operations-professional',
                'tier' => ProgrammeTier::PROFESSIONAL,
                'summary' => 'Digital operations across the business.',
                'duration_label' => '3-month block',
                'duration_days' => 90,
                'weekly_hours' => null,
                'fee_note' => 'Fees and dates on enquiry',
                'content_code' => null,
                'status' => ProgrammeStatus::OPEN,
                'sort_order' => 130,
            ],
            [
                'code' => 'DOPF',
                'name' => 'Digital Operations Professional Foundation',
                'slug' => 'digital-operations-professional-foundation',
                'tier' => ProgrammeTier::FOUNDATION,
                'summary' => 'The 90-day foundation programme delivered through the Naleli Workspace app.',
                'duration_label' => '90-day programme',
                'duration_days' => 90,
                'weekly_hours' => '8-10',
                'fee_note' => 'Fees and dates on enquiry',
                'content_code' => 'digital-foundation',
                'status' => ProgrammeStatus::OPEN,
                'sort_order' => 5,
            ],
            [
                'code' => 'EXCEL',
                'name' => 'Basic Excel',
                'slug' => 'basic-excel',
                'tier' => ProgrammeTier::SHORT_COURSE,
                'summary' => 'Standalone short course in spreadsheet fundamentals.',
                'duration_label' => 'Short course',
                'duration_days' => null,
                'weekly_hours' => null,
                'fee_note' => 'Booked per intake',
                'content_code' => null,
                'status' => ProgrammeStatus::OPEN,
                'sort_order' => 200,
            ],
        ];
    }

    /**
     * One offering per programme.
     *
     * Career Modules carry the confirmed commercial model and open for sale:
     * R500 registration once, then R950 in each of three months — R3,350 in
     * total, billed as four invoices, with the registration fee opening
     * access. This is what the website already advertises.
     *
     * Everything else stays DRAFT at R0 and cannot be sold, because "fees on
     * enquiry" is not a price. Opening one is a deliberate act in the
     * dashboard that shows the resulting invoice shape first.
     */
    private function seedOfferings(): void
    {
        foreach (Programme::all() as $programme) {
            $isCareerModule = $programme->tier === ProgrammeTier::CAREER_MODULE;

            Offering::updateOrCreate(
                ['code' => "{$programme->code}-2027-BLOCK"],
                [
                    'programme_id' => $programme->id,
                    'name' => $isCareerModule
                        ? "{$programme->name} — February 2027"
                        : "{$programme->name} — 3-month block",
                    'description' => $isCareerModule
                        ? 'R500 registration, then R950 per month for three months.'
                        : 'Price not yet confirmed. Fees are on enquiry for this programme.',
                    // The confirmed KCS Career Module model: R500 registration
                    // once, then R950 in each of three months. R3,350 total,
                    // and the registration fee is what opens access.
                    'billing_model' => $isCareerModule
                        ? BillingModel::DEPOSIT_BALANCE
                        : BillingModel::FIXED_BLOCK,
                    'price_cents' => $isCareerModule ? 335000 : 0,
                    'deposit_cents' => $isCareerModule ? 50000 : null,
                    'instalment_count' => $isCareerModule ? 3 : null,
                    'access_duration_days' => 90,
                    'activation_rule' => ActivationRule::ON_FIRST_PAYMENT,
                    'status' => $isCareerModule ? OfferingStatus::OPEN : OfferingStatus::DRAFT,
                    'sort_order' => $programme->sort_order,
                ],
            );
        }
    }

    /**
     * Career Modules run the February 2027 intake advertised on the website.
     * Professional Specialisations are "dates on enquiry", so they carry no
     * intake until one is scheduled.
     */
    private function seedIntakes(): void
    {
        $codes = Programme::where('tier', ProgrammeTier::CAREER_MODULE)->pluck('id', 'code');

        foreach ($codes as $programmeId) {
            Intake::updateOrCreate(
                ['programme_id' => $programmeId, 'label' => 'February 2027'],
                [
                    'starts_on' => '2027-02-01',
                    'ends_on' => '2027-04-30',
                    'status' => IntakeStatus::PLANNED,
                ],
            );
        }
    }

    /**
     * Recorded now, enforced in Phase 5 when completion exists to test
     * against. Professional Specialisations are set to manual approval rather
     * than a guessed prerequisite chain — the Entry Requirements and Career
     * Pathways pages describe the real rules in prose and a subject lead needs
     * to translate them before they gate anybody.
     */
    private function seedRequirements(): void
    {
        foreach (Programme::all() as $programme) {
            $rule = $programme->tier === ProgrammeTier::PROFESSIONAL
                ? RequirementRule::MANUAL_APPROVAL
                : RequirementRule::NONE;

            ProgrammeRequirement::updateOrCreate(
                ['programme_id' => $programme->id],
                [
                    'rule_type' => $rule,
                    'notes' => $rule === RequirementRule::MANUAL_APPROVAL
                        ? 'Entry rules to be confirmed against the Entry Requirements page.'
                        : null,
                ],
            );
        }
    }
}
