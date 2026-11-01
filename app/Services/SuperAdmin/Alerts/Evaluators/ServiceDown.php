<?php

namespace App\Services\SuperAdmin\Alerts\Evaluators;

use App\Enums\Notifications\AlertSeverity;
use App\Models\SuperAdmin\AlertRule;
use App\Services\SuperAdmin\Alerts\AlertEvaluator;
use App\Services\SuperAdmin\Alerts\AlertSignal;
use App\Services\SuperAdmin\System\Checks\HealthCheck;
use App\Services\SuperAdmin\System\HealthChecker;

class ServiceDown implements AlertEvaluator
{
    public function __construct(private readonly HealthChecker $checker) {}

    public static function type(): string
    {
        return 'service_down';
    }

    public static function defaults(): array
    {
        return ['include_degraded' => false];
    }

    public function evaluate(AlertRule $rule): array
    {
        $statuses = [HealthCheck::DOWN];

        if ($rule->setting('include_degraded')) {
            $statuses[] = HealthCheck::DEGRADED;
        }

        $signals = [];

        foreach ($this->checker->run()['checks'] as $name => $check) {
            if (in_array($check['status'], $statuses, true)) {
                $signals[] = new AlertSignal(
                    $name,
                    "Health check \"{$name}\" is {$check['status']}: {$check['message']}",
                    ['check' => $name, 'status' => $check['status'], 'message' => $check['message']],
                    $check['status'] === HealthCheck::DOWN ? AlertSeverity::Critical : AlertSeverity::Warning,
                );
            }
        }

        return $signals;
    }
}
