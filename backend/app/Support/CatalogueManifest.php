<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\ProgrammeStatus;
use App\Enums\ProgrammeTier;

/**
 * The KCS / NIBS catalogue: one Foundation, seven Career Specialisations and
 * five Professional Specialisations. Thirteen three-month blocks, one price.
 *
 * This replaces two catalogues that used to coexist and disagree — a set of
 * KCS short courses and NIBS QCTO programmes sold through the old application
 * form, and a separate list in the site navigation. Both are gone. Anything
 * not declared here is archived by ProgrammeSeeder rather than deleted,
 * because 35 real applications and 15 Excel bookings already name the old
 * items and those records have to keep pointing at something.
 *
 * The commercial model is the same for every block, as instructed:
 * R500 once-off registration, then R950 a month for three months — R3,350,
 * with the registration fee opening access. Nothing here is "fees on enquiry";
 * the Professional Specialisations are priced identically to the rest.
 *
 * ProgrammeSeeder builds from this file and CatalogueDriftTest asserts the
 * database still matches it, so a programme cannot enter the backend without
 * appearing here in a reviewed commit.
 */
class CatalogueManifest
{
    public const AUDITED_ON = '2026-08-30';

    public const SITE = 'https://www.kcs.edu.za/';

    /** R500 once-off registration, then R950 a month for three months. */
    public const PRICE_CENTS = 335000;

    public const DEPOSIT_CENTS = 50000;

    public const INSTALMENTS = 3;

    public const BLOCK_DAYS = 90;

    public const INTAKE_LABEL = 'February 2027';

    public const INTAKE_STARTS = '2027-02-01';

    public const INTAKE_ENDS = '2027-04-30';

    public const FEE_NOTE = 'R500 once-off, then R950 per month';

    /** Every learner starts with the Foundation before specialising. */
    public const FOUNDATION_CODE = 'DOPF';

    /**
     * Which content pack each programme teaches from.
     *
     * A pack is a directory under `content/` holding `course.json` and
     * `workspace-content.json` — the same JSON the Android app bundles and the
     * browser downloads, so a learner sees one body of content whichever they
     * open.
     *
     * Naming every programme here, including the twelve nobody has authored
     * yet, is the point. A declared-but-missing pack is a known gap the
     * backend can report; a programme with no entry at all is a silent one,
     * and silence is how a Payroll learner ends up being shown the Foundation
     * course. `ContentPacks::status()` and `php artisan content:check` read
     * this list and say which are real.
     *
     * @var array<string, string>
     */
    public const CONTENT_PACKS = [
        'DOPF' => 'digital-foundation',
        'PPO' => 'people-payroll-operations',
        'CRM' => 'customer-crm-operations',
        'DMO' => 'digital-marketing-operations',
        'PROJ' => 'project-operations',
        'PROC' => 'procurement-tender-operations',
        'ENT' => 'entrepreneurship-business-operations',
        'ICT' => 'ict-systems-administration',
        'MIS' => 'management-information-systems',
        'OPS' => 'operations-management',
        'CHG' => 'change-management',
        'ERP' => 'erp-systems-administration',
        'BIA' => 'business-intelligence-analytics',
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function programmes(): array
    {
        $out = [];
        $sort = 0;

        foreach (self::rows() as [$code, $name, $slug, $tier, $label, $summary]) {
            $out[] = [
                'code' => $code,
                'name' => $name,
                'slug' => $slug,
                'tier' => $tier,
                'nqf_level' => null,
                'source_url' => self::SITE.'career-pathways/'.$slug.'/',
                'source_note' => $label,
                'summary' => $summary,
                'content_code' => self::CONTENT_PACKS[$code] ?? null,
                'duration_label' => '3-month block',
                'duration_days' => self::BLOCK_DAYS,
                'weekly_hours' => '8-10',
                'fee_note' => self::FEE_NOTE,
                'status' => ProgrammeStatus::OPEN,
                'sort_order' => $sort += 10,
            ];
        }

        return $out;
    }

    /**
     * @return array<int, array{0: string, 1: string, 2: string, 3: ProgrammeTier, 4: string, 5: string}>
     */
    private static function rows(): array
    {
        return [
            [
                'DOPF',
                'Digital Operations Professional Foundation',
                'digital-operations-foundation',
                ProgrammeTier::FOUNDATION,
                'Foundation · compulsory for every learner',
                'Information systems literacy, modern workplace tools, records management and cybersecurity awareness.',
            ],

            // Career Specialisations — seven ways to build a career.
            [
                'PPO',
                'People & Payroll Operations',
                'people-payroll-operations',
                ProgrammeTier::CAREER_MODULE,
                'Career Specialisation · People',
                'HR administration, employee records, payroll and HR systems.',
            ],
            [
                'CRM',
                'Customer & CRM Operations',
                'customer-crm-operations',
                ProgrammeTier::CAREER_MODULE,
                'Career Specialisation · Customer',
                'Customer service, CRM, sales pipelines and customer administration.',
            ],
            [
                'DMO',
                'Digital Marketing Operations',
                'digital-marketing-operations',
                ProgrammeTier::CAREER_MODULE,
                'Career Specialisation · Marketing',
                'Content, social media, campaigns and digital analytics.',
            ],
            [
                'PROJ',
                'Project Operations',
                'project-operations',
                ProgrammeTier::CAREER_MODULE,
                'Career Specialisation · Projects',
                'Project administration, planning, coordination and reporting.',
            ],
            [
                'PROC',
                'Procurement & Tender Operations',
                'procurement-tender-operations',
                ProgrammeTier::CAREER_MODULE,
                'Career Specialisation · Commercial',
                'Suppliers, quotations, procurement and tender administration.',
            ],
            [
                'ENT',
                'Entrepreneurship & Business Operations',
                'entrepreneurship-business-operations',
                ProgrammeTier::CAREER_MODULE,
                'Career Specialisation · Enterprise',
                'SME and business administration, digital business operations.',
            ],
            [
                'ICT',
                'ICT Systems Administration',
                'ict-systems-administration',
                ProgrammeTier::CAREER_MODULE,
                'Career Specialisation · ICT',
                'IT support, users, systems, networks, cloud and troubleshooting.',
            ],

            // Professional Specialisations — beyond administration.
            [
                'MIS',
                'Management Information Systems Professional',
                'management-information-systems-professional',
                ProgrammeTier::PROFESSIONAL,
                'Professional Specialisation · Systems',
                'Business information systems.',
            ],
            [
                'OPS',
                'Operations Management Professional',
                'operations-management-professional',
                ProgrammeTier::PROFESSIONAL,
                'Professional Specialisation · Operations',
                'Processes, workflows and operational improvement.',
            ],
            [
                'CHG',
                'Change Management Professional',
                'change-management-professional',
                ProgrammeTier::PROFESSIONAL,
                'Professional Specialisation · Change',
                'Technology and process adoption, organisational change.',
            ],
            [
                'ERP',
                'ERP Systems Administration Professional',
                'erp-systems-administration-professional',
                ProgrammeTier::PROFESSIONAL,
                'Professional Specialisation · ERP',
                'ERP administration and business systems.',
            ],
            [
                'BIA',
                'Business Intelligence & Analytics Professional',
                'business-intelligence-analytics-professional',
                ProgrammeTier::PROFESSIONAL,
                'Professional Specialisation · Data',
                'Data, reporting, dashboards and decision support.',
            ],
        ];
    }

    /**
     * Every block runs the same intake. One catalogue, one calendar.
     *
     * @return array<string, array<int, array{label: string, starts: string, ends: string}>>
     */
    public static function intakes(): array
    {
        $cohort = [[
            'label' => self::INTAKE_LABEL,
            'starts' => self::INTAKE_STARTS,
            'ends' => self::INTAKE_ENDS,
        ]];

        return array_fill_keys(self::codes(), $cohort);
    }

    /**
     * What the registration form must offer, and nothing else. Keyed by the
     * exact label a learner picks so a submission resolves without
     * translation.
     *
     * @return array<string, string>
     */
    public static function formOptions(): array
    {
        $options = [];

        foreach (self::programmes() as $p) {
            $options[$p['name']] = $p['code'];
        }

        return $options;
    }

    /**
     * @return array<int, string>
     */
    public static function codes(): array
    {
        return array_map(fn (array $p): string => $p['code'], self::programmes());
    }

    /**
     * Every block is sellable at the same price. Kept as a method because
     * CatalogueDriftTest asserts against it and a future tier might not be.
     *
     * @return array<int, string>
     */
    public static function sellableCodes(): array
    {
        return array_values(array_map(
            fn (array $p): string => $p['code'],
            array_filter(self::programmes(), fn (array $p): bool => $p['status'] === ProgrammeStatus::OPEN),
        ));
    }
}
