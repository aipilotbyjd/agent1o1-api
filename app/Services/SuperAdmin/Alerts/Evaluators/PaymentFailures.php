<?php

namespace App\Services\SuperAdmin\Alerts\Evaluators;

use App\Models\Billing\Subscription;
use App\Models\SuperAdmin\AlertRule;
use App\Services\SuperAdmin\Alerts\AlertEvaluator;
use App\Services\SuperAdmin\Alerts\AlertSignal;

class PaymentFailures implements AlertEvaluator
{
    public static function type(): string
    {
        return 'payment_failures';
    }

    public static function defaults(): array
    {
        return ['window_hours' => 24, 'threshold' => 3];
    }

    public function evaluate(AlertRule $rule): array
    {
        $window = (int) $rule->setting('window_hours');

        $failing = Subscription::query()->where('dunning_started_at', '>=', now()->subHours($window))->count();

        if ($failing < (int) $rule->setting('threshold')) {
            return [];
        }

        return [new AlertSignal('platform', "{$failing} subscriptions started failing to pay in the last {$window} hours.", ['subscriptions' => $failing, 'window_hours' => $window])];
    }
}
