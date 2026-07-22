<?php

namespace App\Engine\Trigger;

use App\Enums\ExecutionStatus;
use App\Models\AgentRun;
use App\Models\Execution;
use App\Models\Trigger;

class TriggerConcurrencyGuard
{
    public function canExecute(Trigger $trigger): bool
    {
        $maxConcurrency = max(1, $trigger->max_concurrency);

        return $this->activeRuns($trigger) < $maxConcurrency;
    }

    /**
     * Count in-flight runs for the trigger's target so concurrency limits apply
     * uniformly to workflows and agents.
     */
    private function activeRuns(Trigger $trigger): int
    {
        return match ($trigger->target_type) {
            'agent' => AgentRun::where('agent_id', $trigger->target_id)
                ->whereIn('status', ['pending', 'running'])
                ->count(),
            default => Execution::where('runnable_id', $trigger->target_id)
                ->whereIn('status', [ExecutionStatus::Pending, ExecutionStatus::Running])
                ->count(),
        };
    }
}
