<?php

declare(strict_types=1);

return [

    /*
     * Pay@ Go — the YAPI Merchant "Request to Pay" API.
     *
     * A request-to-pay is a payable reference: we create one per invoice, the
     * learner takes the number (or the QR link) to any Pay@ retail counter,
     * Shoprite to Checkers to Pick n Pay, and pays cash. It is the most
     * accessible method KCS offers — no bank card, no data, no app — which is
     * why it is the first gateway wired up rather than the last.
     */

    'base_url' => env('PAYAT_BASE_URL', 'https://go.payat.co.za/yapi/v1'),
    'token_url' => env('PAYAT_TOKEN_URL', 'https://go.payat.co.za/yapi/oauth/token'),

    /*
     * OAuth2 client credentials. Two things about this endpoint are not
     * guessable and cost an afternoon each if you get them wrong:
     *
     * 1. The credentials go in an HTTP Basic header. Sending them in the POST
     *    body — which is also legal OAuth2 — returns `invalid_client`.
     * 2. The scopes must be requested EXPLICITLY. A token minted without a
     *    `scope` parameter is issued happily and then 403s on every call.
     */
    'client_id' => env('PAYAT_CLIENT_ID', ''),
    'client_secret' => env('PAYAT_CLIENT_SECRET', ''),
    'scopes' => ['rtp:create:single', 'rtp:cancel:single', 'rtp:read'],

    /** Printed on the learner's payment slip at the till. */
    'merchant_display_name' => env('PAYAT_MERCHANT_DISPLAY_NAME', 'Katlehong Computer School'),

    /*
     * The account number is ours to allocate: 14 digits at most, and unique
     * on this merchant account forever. It is derived from the invoice id so
     * it can never collide, and prefixed so it can never be confused with the
     * mobile numbers used as references before this backend existed.
     */
    'account_prefix' => env('PAYAT_ACCOUNT_PREFIX', '9'),
    'account_width' => (int) env('PAYAT_ACCOUNT_WIDTH', 10),

    /** How long a reference stays payable. Pay@ allows 1–120. */
    'days_valid' => (int) env('PAYAT_DAYS_VALID', 60),

    /*
     * The smallest amount a till will accept against the reference. Partial
     * payment is deliberately allowed — turning a learner away at the counter
     * because they are short is worse than carrying a part-paid invoice — but
     * nothing activates until the full amount has arrived.
     */
    'minimum_amount_cents' => (int) env('PAYAT_MINIMUM_AMOUNT_CENTS', 1000),

    'timeout' => (int) env('PAYAT_TIMEOUT', 20),

];
