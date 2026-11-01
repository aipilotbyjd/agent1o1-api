<?php

namespace App\Services\SuperAdmin\Alerts\Evaluators;

use App\Models\SuperAdmin\AlertRule;
use App\Services\SuperAdmin\Alerts\AlertEvaluator;
use App\Services\SuperAdmin\Alerts\AlertSignal;
use App\Services\SuperAdmin\System\FailedJobService;

class FailedJobsGrowth implements AlertEvaluator
{
    public function __construct(private readonly FailedJobService $failedJobs) {}

    public static function type(): string
    {
        return 'failed_jobs_growth';
    }

    public static function defaults(): array
    {
        return ['window_minutes' => 60, 'threshold' => 20];
    }

    public function evaluate(AlertRule $rule): array
    {
        $window = (int) $rule->setting('window_minutes');
        $perQueue = $this->failedJobs->failedPerQueue(now()->subMinutes($window));
        $total = array_sum($perQueue);

        if ($total < (int) $rule->setting('threshold')) {
            return [];
        }

        return [new AlertSignal('platform', "{$total} jobs failed in the last {$window} minutes.", ['failed' => $total, 'by_queue' => $perQueue])];
    }
}
