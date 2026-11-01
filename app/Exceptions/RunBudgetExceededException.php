<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown before a workflow node runs when the run has already used the
 * plan's `max_run_credits`. The node fails without a retry, so a runaway
 * loop stops instead of spending the workspace's whole balance.
 */
class RunBudgetExceededException extends RuntimeException
{
    public function __construct(public readonly int $max, public readonly int $used)
    {
        parent::__construct("This run used {$used} credits and reached the {$max}-credit limit for one run on this plan.");
    }
}
