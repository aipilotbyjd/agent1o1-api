<?php

namespace App\Exceptions;

use App\Enums\Billing\PlanLimit;
use RuntimeException;

/**
 * Thrown by `Services\Billing\PlanLimitGate` when creating a resource would
 * push the workspace past its current plan's cap for that resource: mapped
 * to 402 in `bootstrap/app.php`, the same status as
 * `InsufficientCreditsException`, since both mean "this needs a bigger plan"
 * and the frontend handles them as one upgrade prompt.
 */
class PlanLimitExceededException extends RuntimeException
{
    public function __construct(
        public readonly PlanLimit $limit,
        public readonly int $max,
        public readonly int $used,
    ) {
        parent::__construct("Your plan is limited to {$max} {$limit->label()} and you are using {$used}. Upgrade your plan to add more.");
    }
}
