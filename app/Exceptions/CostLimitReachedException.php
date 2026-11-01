<?php

namespace App\Exceptions;

/**
 * Thrown by `Services\Billing\CostGuard` when new work would pass an AI cost
 * guardrail: the plan's daily or weekly model-cost cap, its limit on runs in
 * flight, or the platform's free-tier budget. Mapped to 429 in
 * `bootstrap/app.php`: the limit resets with time, unlike running out of
 * credits (402).
 */
class CostLimitReachedException extends UsageBlockedException
{
    public function __construct(
        public readonly string $limit,
        string $message,
    ) {
        parent::__construct($message);
    }
}
