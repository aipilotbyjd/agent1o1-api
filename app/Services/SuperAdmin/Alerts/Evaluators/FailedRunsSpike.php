<?php

namespace App\Services\SuperAdmin\Alerts\Evaluators;

use App\Enums\RunStatus;
use App\Models\Runs\Run;
use App\Models\SuperAdmin\AlertRule;
use App\Services\SuperAdmin\Alerts\AlertEvaluator;
use App\Services\SuperAdmin\Alerts\AlertSignal;

/**
 * Failed runs in the last few minutes against the same window at the same
 * hour in previous weeks, so a normally busy Monday morning is not an anomaly
 * and a quiet Sunday night with a burst of failures is.
 */
class FailedRunsSpike implements AlertEvaluator
{
    public static function type(): string
    {
        return 'failed_runs_spike';
    }

    public static function defaults(): array
    {
        return ['window_minutes' => 15, 'min_failures' => 5, 'factor' => 3.0, 'baseline_weeks' => 4];
    }

    public function evaluate(AlertRule $rule): array
    {
        $window = (int) $rule->setting('window_minutes');
        $weeks = max(1, (int) $rule->setting('baseline_weeks'));

        $current = $this->failures(now()->subMinutes($window), now());

        if ($current < (int) $rule->setting('min_failures')) {
            return [];
        }

        $baseline = 0;

        for ($week = 1; $week <= $weeks; $week++) {
            $baseline += $this->failures(now()->subWeeks($week)->subMinutes($window), now()->subWeeks($week));
        }

        $average = $baseline / $weeks;

        if ($current < (float) $rule->setting('factor') * max($average, 1)) {
            return [];
        }

        return [new AlertSignal(
            'platform',
            sprintf('%d runs failed in the last %d minutes, against about %.1f at this time of week.', $current, $window, $average),
            ['failed_runs' => $current, 'window_minutes' => $window, 'baseline_average' => round($average, 1)],
        )];
    }

    private function failures(\DateTimeInterface $from, \DateTimeInterface $to): int
    {
        return Run::query()
            ->where('status', RunStatus::Failed->value)
            ->where('updated_at', '>=', $from)
            ->where('updated_at', '<=', $to)
            ->count();
    }
}
