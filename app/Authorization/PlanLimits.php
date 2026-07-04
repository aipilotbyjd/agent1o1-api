<?php

namespace App\Authorization;

use App\Enums\Limit;
use App\Exceptions\Billing\QuotaExceededException;
use App\Models\Workspace;

final class PlanLimits
{
    public function check(Workspace $workspace, Limit $limit, int $current): void
    {
        $plan = $workspace->currentPlan();
        if (! $plan) {
            return;
        }

        $max = $plan->getLimit($limit);

        if ($max !== -1 && $current >= $max) {
            throw new QuotaExceededException($limit, $max, $current);
        }
    }

    public function value(Workspace $workspace, Limit $limit): LimitValue
    {
        $plan = $workspace->currentPlan();
        $raw = $plan?->getLimit($limit) ?? -1;

        return new LimitValue($raw === -1, $raw === -1 ? null : $raw);
    }
}
