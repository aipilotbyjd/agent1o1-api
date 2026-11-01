<?php

namespace App\Exceptions;

use App\Enums\Billing\Feature;
use RuntimeException;

/**
 * Thrown when an action requires a `Plan.features` flag the workspace's
 * current plan doesn't have: mapped to 403 in `bootstrap/app.php`.
 */
class FeatureNotAvailableException extends RuntimeException
{
    public function __construct(public readonly Feature $feature)
    {
        parent::__construct("This feature ({$feature->value}) is not available on your current plan.");
    }
}
