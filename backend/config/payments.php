<?php

declare(strict_types=1);

use App\Services\Payments\Providers\ManualPaymentProvider;

return [

    /*
     * Every payment method the backend can record. Phase 1 ships manual
     * confirmation only; Ozow, PayJustNow and Payflex are added here as their
     * merchant accounts are approved, each as one class implementing
     * PaymentProvider. Nothing above the interface knows which one settled a
     * payment, so a declined merchant application costs nothing but a line.
     */
    'providers' => [
        ManualPaymentProvider::class,
    ],

    'currency' => 'ZAR',

];
