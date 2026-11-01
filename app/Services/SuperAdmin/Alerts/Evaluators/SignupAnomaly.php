<?php

namespace App\Services\SuperAdmin\Alerts\Evaluators;

use App\Models\SuperAdmin\AlertRule;
use App\Models\User;
use App\Services\SuperAdmin\Alerts\AlertEvaluator;
use App\Services\SuperAdmin\Alerts\AlertSignal;

/**
 * Signups far above what this hour of the week normally brings: usually an
 * abuse wave, occasionally a launch.
 */
class SignupAnomaly implements AlertEvaluator
{
    public static function type(): string
    {
        return 'signup_anomaly';
    }

    public static function defaults(): array
    {
        return ['window_minutes' => 60, 'min_signups' => 20, 'factor' => 5.0, 'baseline_weeks' => 4];
    }

    public function evaluate(AlertRule $rule): array
    {
        $window = (int) $rule->setting('window_minutes');
        $weeks = max(1, (int) $rule->setting('baseline_weeks'));

        $current = $this->signups(now()->subMinutes($window), now());

        if ($current < (int) $rule->setting('min_signups')) {
            return [];
        }

        $baseline = 0;

        for ($week = 1; $week <= $weeks; $week++) {
            $baseline += $this->signups(now()->subWeeks($week)->subMinutes($window), now()->subWeeks($week));
        }

        $average = $baseline / $weeks;

        if ($current < (float) $rule->setting('factor') * max($average, 1)) {
            return [];
        }

        return [new AlertSignal('platform', sprintf('%d signups in the last %d minutes, against about %.1f at this time of week.', $current, $window, $average), ['signups' => $current, 'baseline_average' => round($average, 1)])];
    }

    private function signups(\DateTimeInterface $from, \DateTimeInterface $to): int
    {
        return User::query()->where('created_at', '>=', $from)->where('created_at', '<=', $to)->count();
    }
}
