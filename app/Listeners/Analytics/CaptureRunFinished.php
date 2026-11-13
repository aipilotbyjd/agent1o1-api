<?php

namespace App\Listeners\Analytics;

use App\Events\Runs\RunCompleted;
use App\Events\Runs\RunFailed;
use App\Models\Runs\Run;
use App\Services\Analytics\ProductAnalytics;

/**
 * Activation and engagement: every finished run, whatever started it (a user,
 * a trigger, a parent run). Runs with no `triggered_by` are anonymous system
 * events attached to the workspace group.
 */
class CaptureRunFinished
{
    public function __construct(private readonly ProductAnalytics $analytics) {}

    public function handle(RunCompleted $event): void
    {
        $this->capture('run_completed', $event->run);
    }

    public function handleRunFailed(RunFailed $event): void
    {
        $this->capture('run_failed', $event->run);
    }

    private function capture(string $name, Run $run): void
    {
        $this->analytics->capture(
            $run->triggered_by !== null ? (string) $run->triggered_by : null,
            $name,
            [
                'run_id' => (string) $run->getKey(),
                'runnable_type' => $run->runnable_type,
                'trigger_type' => $run->trigger_type,
                'is_child_run' => $run->parent_run_id !== null,
                'duration_ms' => $run->durationMs(),
            ],
            (string) $run->workspace_id,
        );
    }
}
