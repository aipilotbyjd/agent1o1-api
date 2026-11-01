<?php

namespace App\Services\SuperAdmin\Alerts\Evaluators;

use App\Models\SuperAdmin\AlertRule;
use App\Services\SuperAdmin\Alerts\AlertEvaluator;
use App\Services\SuperAdmin\Alerts\AlertSignal;
use App\Services\SuperAdmin\System\SchedulerMonitor;

/**
 * The scheduler's heartbeat has gone stale. This evaluator runs inside the
 * scheduler, so it can only fire when something else (a manual `alerts:evaluate`,
 * or a second scheduler) is still alive: the daily digest and System Health
 * are the backstops for a fully dead one.
 */
class SchedulerDead implements AlertEvaluator
{
    public function __construct(private readonly SchedulerMonitor $scheduler) {}

    public static function type(): string
    {
        return 'scheduler_dead';
    }

    public static function defaults(): array
    {
        return ['stale_seconds' => 180];
    }

    public function evaluate(AlertRule $rule): array
    {
        $beat = $this->scheduler->heartbeatAt();

        if ($beat === null) {
            return [];
        }

        $age = (int) $beat->diffInSeconds(now(), true);

        if ($age <= (int) $rule->setting('stale_seconds')) {
            return [];
        }

        return [new AlertSignal('scheduler', "The scheduler has not run for {$age} seconds.", ['age_seconds' => $age, 'last_heartbeat_at' => $beat->toIso8601String()])];
    }
}
