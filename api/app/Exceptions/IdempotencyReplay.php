<?php

namespace App\Exceptions;

use RuntimeException;

class IdempotencyReplay extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $body
     */
    public function __construct(public array $body, public int $status)
    {
        parent::__construct('Idempotency replay');
    }
}
