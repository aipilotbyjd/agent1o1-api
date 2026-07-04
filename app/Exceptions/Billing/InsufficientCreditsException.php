<?php

namespace App\Exceptions\Billing;

use Exception;

class InsufficientCreditsException extends Exception
{
    public function __construct(int $needed, int $available)
    {
        parent::__construct(
            "Insufficient credits: need {$needed}, have {$available}.",
            402,
        );
    }
}
