<?php

declare(strict_types=1);

return [

    /*
     * The shared secret Fluent Forms signs its request body with. Generate a
     * long random value, put it here via the environment, and paste the same
     * value into the webhook integration on form 8 at kcs.edu.za.
     */
    'fluentform' => [
        'secret' => env('KCS_FLUENTFORM_SECRET', ''),

        /*
         * Form 8 spreads the programme choice across four fields: a pathway
         * radio, then one of three dropdowns depending on which pathway was
         * chosen. The option VALUES on those dropdowns are free text set in
         * the Fluent Forms editor, so they must be mapped explicitly to
         * programme codes — a mismatch files an application against the wrong
         * programme silently.
         *
         * This map is filled in from an export of the live form's options
         * (build sequence step 5) and reviewed before the webhook goes live.
         * Anything unmapped is held for a registrar rather than guessed at.
         */
        'programme_map' => [
            // Read off the live Student Application Form on 30 August 2026,
            // and cross-checked against the 35 submissions it has already
            // taken — the counts below are how many real applicants chose
            // each option. A string not in this map is held for a registrar
            // rather than filed against a guess.

            // "Choose Your Professional Programme" — KCS short courses.
            'Digital Office Administrator' => 'DOA',        // 8 applicants
            'IT Support Specialist' => 'ITS',               // 5 applicants
            'Data Capturing Specialist' => 'DCS',           // 1 applicant
            'Junior Software Developer' => 'JSD',           // 1 applicant
            'Computer Technician' => 'CT',
            'Network Technician' => 'NT',
            'Junior Cybersecurity Analyst' => 'JCA',        // 1 applicant
            'Cybersecurity Analyst' => 'JCA',               // 3 applicants, shorter label
            'Ethical Hacker' => 'EH',
            'Junior AI & Data Analyst' => 'JAI',
            'Office Administration' => 'OA',                // 1 applicant, QCTO by another name

            // "Choose Your Bootcamp" — NIBS QCTO, posted with the full label.
            'OA-001 | Office Administrator | NQF 5 | 16 Apr 2026 - 15 Apr 2027' => 'OA',
            'OA-002 | Office Administrator | NQF 5 | 16 May 2026 - 15 May 2027' => 'OA',
            'MA-001 | Management Assistant | NQF 5 | 16 Apr 2026 - 15 Apr 2027' => 'MA',
            'MA-002 | Management Assistant | NQF 5 | 16 May 2026 - 15 May 2027' => 'MA',
            'REA-001 | Real Estate Agent | NQF 4 | 16 Apr 2026 - 15 Apr 2027' => 'REA',
            'REA-002 | Real Estate Agent | NQF 4 | 16 May 2026 - 15 May 2027' => 'REA',
            'BK-001 | Bookkeeper | NQF 5 | 16 Apr 2026 - 15 Apr 2027' => 'BK',
            'BK-002 | Bookkeeper | NQF 5 | 16 May 2026 - 15 May 2027' => 'BK',
            'SBC-001 | Small Business Consultant | NQF 5 | 16 Apr 2026 - 15 Apr 2027' => 'SBC',
            'SBC-002 | Small Business Consultant | NQF 5 | 16 May 2026 - 15 May 2027' => 'SBC',
            'QA-001 | Quality Assurer | NQF 5 | 16 Apr 2026 - 15 Apr 2027' => 'QA',
            'QA-002 | Quality Assurer | NQF 5 | 16 May 2026 - 15 May 2027' => 'QA',
            'QM-001 | Quality Manager | NQF 6 | 16 Apr 2026 - 15 Oct 2027' => 'QM',
            'QM-002 | Quality Manager | NQF 6 | 16 May 2026 - 15 Nov 2027' => 'QM',
            'NVC-001 | New Venture Creation | NQF 2 | 16 Apr 2026 - 15 Sep 2027' => 'NVC',
            'NVC-002 | New Venture Creation | NQF 2 | 16 May 2026 - 15 Oct 2027' => 'NVC',

            // Core and Special Skills, matched on the code that starts the
            // label; the full option text carries the cohort dates.
            'CS1' => 'CS1',
            'CS2' => 'CS2',
            'CS3' => 'CS3',
            'SS1' => 'SS1',
            'SS2' => 'SS2',
            'SS3' => 'SS3',

            // Career pathways, in case the form is ever extended to sell them.
            // Today it does not, which is why no application has ever named one.
            'People & Payroll Operations' => 'PPO',
            'Customer & CRM Operations' => 'CRM',
            'Procurement & Tender Operations' => 'PROC',
            'Entrepreneurship & Business Operations' => 'ENT',
            'Digital Marketing Operations' => 'DMO',
            'Project Operations' => 'PROJ',
            'ICT Systems Administration' => 'ICT',
            'Basic Excel' => 'EXCEL',
        ],
    ],

];
