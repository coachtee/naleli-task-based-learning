<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\ProgrammeStatus;
use App\Enums\ProgrammeTier;

/**
 * The catalogue, as published on kcs.edu.za, audited on 30 August 2026.
 *
 * This file is the single declared list. ProgrammeSeeder builds from it and
 * CatalogueDriftTest asserts the database still matches it, so the backend
 * cannot quietly grow a programme the school does not offer — or keep one it
 * has stopped offering — without this file changing in a reviewed commit.
 *
 * Two catalogues were found on the live site and they do not agree:
 *
 *  - The navigation publishes thirteen Career Pathways plus a qualification,
 *    each as a page that exists but has no content on it at all.
 *  - The Student Application Form — the form that has taken 35 real
 *    submissions — sells an entirely different list: KCS short courses, NIBS
 *    QCTO programmes, and modular Core and Special Skills courses.
 *
 * Not one of those 35 applications names a Career Pathway. Both lists are
 * recorded here because both are genuinely published; what separates them is
 * `source_note`, which says which surface each came from, and `status`, which
 * says whether the site publishes a price that would let it be sold.
 *
 * Prices are only ever what the site itself states. Where kcs.edu.za publishes
 * no price the programme is seeded DRAFT at zero, because a draft offering
 * cannot be sold — that refusal is the point, not an oversight to tidy away.
 */
class CatalogueManifest
{
    public const AUDITED_ON = '2026-08-30';

    public const APPLICATION_FORM = 'https://www.kcs.edu.za/application/';

    /** R500 once-off registration, then R950 a month for three months. */
    public const CAREER_MODULE_PRICE_CENTS = 335000;

    public const CAREER_MODULE_DEPOSIT_CENTS = 50000;

    /**
     * Every programme the live site publishes, with where it was found.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function programmes(): array
    {
        return array_merge(
            self::shortCourses(),
            self::qctoProgrammes(),
            self::modularSkills(),
            self::careerPathways(),
        );
    }

    /**
     * "KCS Short Course" on the application form. Real applicants chose from
     * this list: Digital Office Administrator (8), IT Support Specialist (5),
     * Junior Software Developer, Data Capturing Specialist, Junior
     * Cybersecurity Analyst. The site publishes no price for any of them.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function shortCourses(): array
    {
        $courses = [
            ['DOA', 'Digital Office Administrator', 'digital-office-administrator'],
            ['ITS', 'IT Support Specialist', 'it-support-specialist'],
            ['DCS', 'Data Capturing Specialist', 'data-capturing-specialist'],
            ['CT', 'Computer Technician', 'computer-technician'],
            ['NT', 'Network Technician', 'network-technician'],
            ['JCA', 'Junior Cybersecurity Analyst', 'junior-cybersecurity-analyst'],
            ['EH', 'Ethical Hacker', 'ethical-hacker'],
            ['JAI', 'Junior AI & Data Analyst', 'junior-ai-data-analyst'],
            ['JSD', 'Junior Software Developer', 'junior-software-developer'],
        ];

        $out = [];
        $sort = 100;

        foreach ($courses as [$code, $name, $slug]) {
            $out[] = [
                'code' => $code,
                'name' => $name,
                'slug' => $slug,
                'tier' => ProgrammeTier::SHORT_COURSE,
                'nqf_level' => null,
                'source_url' => self::APPLICATION_FORM,
                'source_note' => 'Application form — KCS Short Course',
                'summary' => null,
                'duration_label' => null,
                'duration_days' => null,
                'fee_note' => 'No price published on kcs.edu.za',
                'status' => ProgrammeStatus::DRAFT,
                'sort_order' => $sort += 10,
                'price_cents' => 0,
            ];
        }

        // The one short course the site does price, on its own booking page
        // and its own live form.
        $out[] = [
            'code' => 'EXCEL',
            'name' => 'Basic Excel',
            'slug' => 'basic-excel',
            'tier' => ProgrammeTier::SHORT_COURSE,
            'nqf_level' => null,
            'source_url' => 'https://www.kcs.edu.za/basic-excel/',
            'source_note' => 'Basic Excel page — R500, cash or EFT',
            'summary' => 'Create spreadsheets, use formulas and functions, organise data, make simple charts.',
            'duration_label' => 'Short course',
            'duration_days' => null,
            'fee_note' => 'R500',
            'status' => ProgrammeStatus::OPEN,
            'sort_order' => 190,
            'price_cents' => 50000,
        ];

        return $out;
    }

    /**
     * "NIBS QCTO Programme" on the application form, with the NQF levels the
     * form itself states. Two intakes each, April and May 2026.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function qctoProgrammes(): array
    {
        $programmes = [
            ['OA', 'Office Administrator', 'office-administrator', '5'],
            ['MA', 'Management Assistant', 'management-assistant', '5'],
            ['REA', 'Real Estate Agent', 'real-estate-agent', '4'],
            ['BK', 'Bookkeeper', 'bookkeeper', '5'],
            ['SBC', 'Small Business Consultant', 'small-business-consultant', '5'],
            ['QA', 'Quality Assurer', 'quality-assurer', '5'],
            ['QM', 'Quality Manager', 'quality-manager', '6'],
            ['NVC', 'New Venture Creation', 'new-venture-creation', '2'],
        ];

        $out = [];
        $sort = 200;

        foreach ($programmes as [$code, $name, $slug, $nqf]) {
            $out[] = [
                'code' => $code,
                'name' => $name,
                'slug' => $slug,
                'tier' => ProgrammeTier::QCTO,
                'nqf_level' => $nqf,
                'source_url' => self::APPLICATION_FORM,
                'source_note' => "Application form — NIBS QCTO, NQF {$nqf}",
                'summary' => null,
                'duration_label' => '12 months',
                'duration_days' => 365,
                'fee_note' => 'No price published on kcs.edu.za',
                'status' => ProgrammeStatus::DRAFT,
                'sort_order' => $sort += 10,
                'price_cents' => 0,
            ];
        }

        return $out;
    }

    /**
     * "Core Skills" and "Special Skills" on the application form — two-week
     * modular courses running April and May 2026.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function modularSkills(): array
    {
        $modules = [
            ['CS1', 'Level 1 Beginners — Operate Computers', 'level-1-beginners', 'Core Skills'],
            ['CS2', 'Level 2 Intermediate — Hardware, Software & Online Tools', 'level-2-intermediate', 'Core Skills'],
            ['CS3', 'Level 3 Advanced — Intro Cybersecurity & Cloud', 'level-3-advanced', 'Core Skills'],
            ['SS1', 'Level 4 IT Support Specialist', 'level-4-it-support-specialist', 'Special Skills'],
            ['SS2', 'Level 5 Digital Marketer', 'level-5-digital-marketer', 'Special Skills'],
            ['SS3', 'Level 6 Online Entrepreneur', 'level-6-online-entrepreneur', 'Special Skills'],
        ];

        $out = [];
        $sort = 300;

        foreach ($modules as [$code, $name, $slug, $group]) {
            $out[] = [
                'code' => $code,
                'name' => $name,
                'slug' => $slug,
                'tier' => ProgrammeTier::MODULAR_SKILL,
                'nqf_level' => null,
                'source_url' => self::APPLICATION_FORM,
                'source_note' => "Application form — {$group} (modular)",
                'summary' => null,
                'duration_label' => '2 weeks',
                'duration_days' => 12,
                'fee_note' => 'No price published on kcs.edu.za',
                'status' => ProgrammeStatus::DRAFT,
                'sort_order' => $sort += 10,
                'price_cents' => 0,
            ];
        }

        return $out;
    }

    /**
     * The site navigation's Career Pathways and the Digital Operations
     * Professional qualification.
     *
     * Every one of these pages is published, linked from the primary menu, and
     * completely empty — no description, no duration, no price. The home page
     * is the only place the commercial model appears: "NIBS Career Modules —
     * R500 once-off registration, R950 a month". Nobody has ever applied for
     * one, because the application form does not offer them.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function careerPathways(): array
    {
        $pathways = [
            ['DOPF', 'Digital Operations Professional Foundation', 'digital-operations-foundation', ProgrammeTier::FOUNDATION],
            ['PPO', 'People & Payroll Operations', 'people-payroll-operations', ProgrammeTier::CAREER_MODULE],
            ['CRM', 'Customer & CRM Operations', 'customer-crm-operations', ProgrammeTier::CAREER_MODULE],
            ['PROJ', 'Project Operations', 'project-operations', ProgrammeTier::CAREER_MODULE],
            ['PROC', 'Procurement & Tender Operations', 'procurement-tender-operations', ProgrammeTier::CAREER_MODULE],
            ['ENT', 'Entrepreneurship & Business Operations', 'entrepreneurship-business-operations', ProgrammeTier::CAREER_MODULE],
            ['DMO', 'Digital Marketing Operations', 'digital-marketing-operations', ProgrammeTier::CAREER_MODULE],
            ['ICT', 'ICT Systems Administration', 'ict-systems-administration', ProgrammeTier::CAREER_MODULE],
            ['MIS', 'Management Information Systems Professional', 'management-information-systems-professional', ProgrammeTier::PROFESSIONAL],
            ['OPS', 'Operations Management Professional', 'operations-management-professional', ProgrammeTier::PROFESSIONAL],
            ['CHG', 'Change Management Professional', 'change-management-professional', ProgrammeTier::PROFESSIONAL],
            ['ERP', 'ERP Systems Administration Professional', 'erp-systems-administration-professional', ProgrammeTier::PROFESSIONAL],
            ['BIA', 'Business Intelligence & Analytics Professional', 'business-intelligence-analytics-professional', ProgrammeTier::PROFESSIONAL],
        ];

        $out = [];
        $sort = 400;

        foreach ($pathways as [$code, $name, $slug, $tier]) {
            $isCareerModule = $tier === ProgrammeTier::CAREER_MODULE;

            $out[] = [
                'code' => $code,
                'name' => $name,
                'slug' => $slug,
                'tier' => $tier,
                'nqf_level' => null,
                'source_url' => "https://www.kcs.edu.za/career-pathways/{$slug}/",
                'source_note' => 'Site navigation — page published but empty',
                'summary' => null,
                'duration_label' => '3-month block',
                'duration_days' => 90,
                'fee_note' => $isCareerModule
                    ? 'R500 once-off registration, then R950 a month (home page)'
                    : 'No price published on kcs.edu.za',
                'status' => $isCareerModule ? ProgrammeStatus::OPEN : ProgrammeStatus::DRAFT,
                'sort_order' => $sort += 10,
                'price_cents' => $isCareerModule ? self::CAREER_MODULE_PRICE_CENTS : 0,
            ];
        }

        // The qualification the pathways build towards, published under
        // /qualifications/ rather than /career-pathways/.
        $out[] = [
            'code' => 'DOP',
            'name' => 'Digital Operations Professional',
            'slug' => 'digital-operations-professional',
            'tier' => ProgrammeTier::PROFESSIONAL,
            'nqf_level' => null,
            'source_url' => 'https://www.kcs.edu.za/qualifications/digital-operations-professional/',
            'source_note' => 'Site navigation — page published but empty',
            'summary' => null,
            'duration_label' => null,
            'duration_days' => null,
            'fee_note' => 'No price published on kcs.edu.za',
            'status' => ProgrammeStatus::DRAFT,
            'sort_order' => 590,
            'price_cents' => 0,
        ];

        return $out;
    }

    /**
     * The cohorts the application form actually offers, exactly as it labels
     * them. These replace an invented "February 2027" intake that appeared
     * nowhere on the site.
     *
     * Only cohorts read directly off the live form are listed. Network
     * Technician and Junior AI & Data Analyst are sold on the form but their
     * cohort dropdowns were not readable in the audit, so they carry no intake
     * rather than a guessed one.
     *
     * @return array<string, array<int, array{label: string, starts: string, ends: string}>>
     */
    public static function intakes(): array
    {
        $qcto = fn (string $code, string $endOne, string $endTwo): array => [
            ['label' => "{$code}-001", 'starts' => '2026-04-16', 'ends' => $endOne],
            ['label' => "{$code}-002", 'starts' => '2026-05-16', 'ends' => $endTwo],
        ];

        $modular = fn (string $code): array => [
            ['label' => "{$code}-001", 'starts' => '2026-04-05', 'ends' => '2026-04-16'],
            ['label' => "{$code}-002", 'starts' => '2026-05-05', 'ends' => '2026-05-16'],
        ];

        return [
            // KCS short courses, by their own cohort codes.
            'DOA' => [
                ['label' => 'DOA-F2F-001', 'starts' => '2026-02-09', 'ends' => '2026-05-08'],
                ['label' => 'DOA-F2F-002', 'starts' => '2026-05-11', 'ends' => '2026-08-10'],
                ['label' => 'DOA-F2F-003', 'starts' => '2026-08-12', 'ends' => '2026-11-11'],
            ],
            'ITS' => [
                ['label' => 'ITS-ON-001', 'starts' => '2026-04-05', 'ends' => '2026-08-04'],
                ['label' => 'ITS-ON-002', 'starts' => '2026-08-05', 'ends' => '2026-12-04'],
            ],
            'DCS' => [
                ['label' => 'DCS-ON-001', 'starts' => '2026-04-05', 'ends' => '2026-05-10'],
                ['label' => 'DCS-ON-002', 'starts' => '2026-05-11', 'ends' => '2026-06-15'],
            ],
            'CT' => [
                ['label' => 'CT-ON-001', 'starts' => '2026-04-05', 'ends' => '2026-08-04'],
                ['label' => 'CT-ON-002', 'starts' => '2026-08-05', 'ends' => '2026-12-04'],
            ],
            'JCA' => [
                ['label' => 'JCA-ON-001', 'starts' => '2026-04-05', 'ends' => '2026-10-04'],
                ['label' => 'JCA-ON-002', 'starts' => '2026-10-05', 'ends' => '2027-04-04'],
            ],
            'EH' => [
                ['label' => 'EH-ON-001', 'starts' => '2026-04-05', 'ends' => '2026-10-04'],
                ['label' => 'EH-ON-002', 'starts' => '2026-10-05', 'ends' => '2027-04-04'],
            ],
            'JSD' => [
                ['label' => 'JSD-ON-001', 'starts' => '2026-04-05', 'ends' => '2026-10-04'],
                ['label' => 'JSD-ON-002', 'starts' => '2026-10-05', 'ends' => '2027-04-04'],
            ],

            // NIBS QCTO programmes. Most run a year; Quality Manager and New
            // Venture Creation run longer, as the form states.
            'OA' => $qcto('OA', '2027-04-15', '2027-05-15'),
            'MA' => $qcto('MA', '2027-04-15', '2027-05-15'),
            'REA' => $qcto('REA', '2027-04-15', '2027-05-15'),
            'BK' => $qcto('BK', '2027-04-15', '2027-05-15'),
            'SBC' => $qcto('SBC', '2027-04-15', '2027-05-15'),
            'QA' => $qcto('QA', '2027-04-15', '2027-05-15'),
            'QM' => $qcto('QM', '2027-10-15', '2027-11-15'),
            'NVC' => $qcto('NVC', '2027-09-15', '2027-10-15'),

            // Two-week modular courses.
            'CS1' => $modular('CS1'),
            'CS2' => $modular('CS2'),
            'CS3' => $modular('CS3'),
            'SS1' => $modular('SS1'),
            'SS2' => $modular('SS2'),
            'SS3' => $modular('SS3'),
        ];
    }

    /**
     * Every code the site publishes. Anything in the database outside this
     * list is drift.
     *
     * @return array<int, string>
     */
    public static function codes(): array
    {
        return array_map(fn (array $p): string => $p['code'], self::programmes());
    }

    /**
     * Codes the site publishes a price for, and which may therefore be sold.
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
