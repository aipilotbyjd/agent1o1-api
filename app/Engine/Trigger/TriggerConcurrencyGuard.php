<?php

namespace App\Engine\Trigger;

use App\Enums\ExecutionStatus;
use App\Models\Execution;
use App\Models\Trigger;

class TriggerConcurrencyGuard
{
    public function canExecute(Trigger $trigger): bool
    {
        $maxConcurrency = max(1, $trigger->max_concurrency);

        $active = Execution::where('workflow_id', $trigger->workflow_id)
            ->whereIn('status', [ExecutionStatus::Pending, ExecutionStatus::Running])
            ->count();

        return $active < $maxConcurrency;
    }
}
