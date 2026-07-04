<?php

namespace App\Exceptions\Billing;

use App\Enums\Feature;
use Exception;

class FeatureNotAvailableException extends Exception
{
    public function __construct(Feature $feature)
    {
        parent::__construct(
            "Feature '{$feature->value}' is not available on your current plan.",
            402,
        );
    }
}
