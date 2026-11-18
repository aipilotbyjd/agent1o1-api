<?php

namespace App\Listeners\Analytics;

use App\Enums\RunStatus;
use App\Events\Runs\RunCompleted;
use App\Events\Runs\RunFailed;
use App\Models\Runs\Run;
use App\Models\User;
use App\Services\Analytics\ProductAnalytics;
use Illuminate\Support\Carbon;

/**
 * Activation and engagement: every finished run, whatever started it (a user,
 * a trigger, a parent run), plus `first_run_succeeded` the first time a
 * person's own run completes. Runs with no `triggered_by` are anonymous
 * system events attached to the workspace group.
 */
class CaptureRunFinished
{
    public function __construct(private readonly ProductAnalytics $analytics) {}

    public function handle(RunCompleted $event): void
    {
        $this->capture('run_completed', $event->run);
        $this->captureFirstSuccess($event->run);
    }

    public function handleRunFailed(RunFailed $event): void
    {
        $this->capture('run_failed', $event->run);
    }

    /**
     * The activation moment: the first run a person started that finished
     * successfully. Child runs and runs nobody started (triggers, schedules)
     * don't count, because the person didn't see them happen.
     */
    private function captureFirstSuccess(Run $run): void
    {
        if ($run->triggered_by === null || $run->parent_run_id !== null) {
            return;
        }

        $earlierSuccess = Run::query()
            ->where('triggered_by', $run->triggered_by)
            ->whereNull('parent_run_id')
            ->where('status', RunStatus::Completed)
            ->whereKeyNot($run->getKey())
            ->exists();

        if ($earlierSuccess) {
            return;
        }

        $signedUpAt = User::query()->whereKey($run->triggered_by)->value('created_at');

        $this->analytics->capture(
            (string) $run->triggered_by,
            'first_run_succeeded',
            [
                'run_id' => (string) $run->getKey(),
                'runnable_type' => $run->runnable_type,
                'seconds_since_signup' => $signedUpAt !== null ? (int) Carbon::parse($signedUpAt)->diffInSeconds(now()) : null,
            ],
            (string) $run->workspace_id,
        );
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
