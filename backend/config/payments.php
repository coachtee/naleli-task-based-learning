<?php

declare(strict_types=1);

use App\Services\Payments\Providers\EftProvider;
use App\Services\Payments\Providers\ManualPaymentProvider;
use App\Services\Payments\Providers\PayAtGoProvider;

return [

    /*
     * Every payment method the backend can record.
     *
     * Pay@ Go and EFT are here because they already work at KCS today and must
     * keep working — accessibility matters more than automation for a learner
     * paying cash at a counter.
     *
     * PayJustNow and Payflex are deliberately absent. They are added here as
     * one class each once merchant approval and integration documents are in
     * hand, and nothing above the PaymentProvider interface knows which
     * provider settled a payment — so a declined application costs a line, not
     * a redesign.
     */
    'providers' => [
        PayAtGoProvider::class,
        EftProvider::class,
        ManualPaymentProvider::class,
    ],

    'currency' => 'ZAR',

];
