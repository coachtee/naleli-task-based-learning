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
            // 'People & Payroll Operations' => 'PPO',
        ],
    ],

];
