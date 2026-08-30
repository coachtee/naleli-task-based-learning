<?php

declare(strict_types=1);

namespace App\Services\Payments\PayAtGo;

use RuntimeException;

/** Pay@ said no, or said nothing. Always carries what it actually returned. */
class PayAtGoException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $response
     */
    public function __construct(
        string $message,
        public readonly int $status = 0,
        public readonly array $response = [],
    ) {
        parent::__construct($message);
    }
}
