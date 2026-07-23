<?php

namespace App\Engine\Trigger;

use App\Enums\ExecutionStatus;
use App\Models\Run;
use App\Models\Trigger;

class TriggerConcurrencyGuard
{
    public function canExecute(Trigger $trigger): bool
    {
        $maxConcurrency = max(1, $trigger->max_concurrency);

        return $this->activeRuns($trigger) < $maxConcurrency;
    }

    /**
     * Count in-flight runs for the trigger's target (workflow or agent) so
     * concurrency limits apply uniformly via the unified Run model.
     */
    private function activeRuns(Trigger $trigger): int
    {
        return Run::where('runnable_id', $trigger->target_id)
            ->whereIn('status', [ExecutionStatus::Pending, ExecutionStatus::Running])
            ->count();
    }
}
