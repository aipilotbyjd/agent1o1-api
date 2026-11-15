<?php

namespace App\Services\SuperAdmin\Operations\Checks;

use App\Models\Billing\StripeWebhookFailure;
use App\Services\SuperAdmin\Operations\CheckResult;
use App\Services\SuperAdmin\Operations\OperationsCheck;

class StripeWebhooksCheck implements OperationsCheck
{
    public function key(): string
    {
        return 'stripe_webhooks';
    }

    public function evaluate(): CheckResult
    {
        $failures = StripeWebhookFailure::query()->unresolved();
        $count = (clone $failures)->count();

        if ($count === 0) {
            return CheckResult::ok('No Stripe events are failing.');
        }

        $oldest = (clone $failures)->min('first_failed_at');
        $hours = (int) config('operations.checks.webhook_fail_after_hours');
        $label = "{$count} Stripe ".($count === 1 ? 'event has' : 'events have').' failed';

        return $oldest !== null && now()->diffInHours($oldest, true) >= $hours
            ? CheckResult::fail("{$label}, the oldest for over {$hours} hours.")
            : CheckResult::warn("{$label} and need replaying.");
    }
}
