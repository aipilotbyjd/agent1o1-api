<?php

namespace App\Services\SuperAdmin\Alerts\Evaluators;

use App\Enums\Notifications\AlertSeverity;
use App\Models\Billing\StripeWebhookFailure;
use App\Models\SuperAdmin\AlertRule;
use App\Services\SuperAdmin\Alerts\AlertEvaluator;
use App\Services\SuperAdmin\Alerts\AlertSignal;

/**
 * Every Stripe event our handler is still failing on is its own alert, and
 * resolves when the event is finally processed or replayed.
 */
class WebhookFailure implements AlertEvaluator
{
    public static function type(): string
    {
        return 'webhook_failure';
    }

    public static function defaults(): array
    {
        return ['critical_after_attempts' => 3];
    }

    public function evaluate(AlertRule $rule): array
    {
        return StripeWebhookFailure::query()->unresolved()->orderByDesc('last_failed_at')->limit(50)->get()
            ->map(fn (StripeWebhookFailure $failure): AlertSignal => new AlertSignal(
                $failure->stripe_event_id,
                "Stripe event {$failure->type} could not be processed ({$failure->attempts} attempt(s)).",
                ['event_id' => $failure->stripe_event_id, 'type' => $failure->type, 'error' => $failure->error, 'attempts' => $failure->attempts],
                $failure->attempts >= (int) $rule->setting('critical_after_attempts') ? AlertSeverity::Critical : null,
            ))
            ->all();
    }
}
