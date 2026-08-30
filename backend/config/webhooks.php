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
            // One catalogue. The registration form offers these thirteen and
            // nothing else, so the label a learner picks is the programme name
            // itself and resolves without translation.
            'Digital Operations Professional Foundation' => 'DOPF',

            'People & Payroll Operations' => 'PPO',
            'Customer & CRM Operations' => 'CRM',
            'Digital Marketing Operations' => 'DMO',
            'Project Operations' => 'PROJ',
            'Procurement & Tender Operations' => 'PROC',
            'Entrepreneurship & Business Operations' => 'ENT',
            'ICT Systems Administration' => 'ICT',

            'Management Information Systems Professional' => 'MIS',
            'Operations Management Professional' => 'OPS',
            'Change Management Professional' => 'CHG',
            'ERP Systems Administration Professional' => 'ERP',
            'Business Intelligence & Analytics Professional' => 'BIA',

        ],
    ],

];
