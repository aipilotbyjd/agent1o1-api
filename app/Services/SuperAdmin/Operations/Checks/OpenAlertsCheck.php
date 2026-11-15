<?php

namespace App\Services\SuperAdmin\Operations\Checks;

use App\Enums\Notifications\AlertSeverity;
use App\Models\SuperAdmin\AlertEvent;
use App\Services\SuperAdmin\Operations\CheckResult;
use App\Services\SuperAdmin\Operations\OperationsCheck;

class OpenAlertsCheck implements OperationsCheck
{
    public function key(): string
    {
        return 'open_alerts';
    }

    public function evaluate(): CheckResult
    {
        $active = AlertEvent::query()->active()->get(['id', 'severity', 'status']);

        if ($active->isEmpty()) {
            return CheckResult::ok('No open alerts.');
        }

        $critical = $active->where('severity', AlertSeverity::Critical)->count();
        $unseen = $active->where('status', AlertEvent::STATUS_OPEN)->count();

        if ($critical > 0) {
            return CheckResult::fail("{$critical} critical ".($critical === 1 ? 'alert is' : 'alerts are')." open ({$active->count()} in all).");
        }

        return CheckResult::warn("{$active->count()} ".($active->count() === 1 ? 'alert is' : 'alerts are')." open, {$unseen} not yet acknowledged.");
    }
}
