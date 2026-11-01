<?php

namespace App\Services\SuperAdmin\System\Checks;

use App\Models\Billing\StripeWebhookFailure;

/**
 * Keys present and no webhook events our handler is failing on. It does not
 * call Stripe: a live API call on every snapshot would cost rate limit.
 */
class StripeCheck implements HealthCheck
{
    public function name(): string
    {
        return 'stripe';
    }

    public function run(): array
    {
        if (blank(config('cashier.secret'))) {
            return ['status' => self::SKIPPED, 'message' => 'Stripe is not configured.'];
        }

        $open = StripeWebhookFailure::query()->unresolved()->count();

        if ($open > 0) {
            return ['status' => self::DEGRADED, 'message' => "{$open} Stripe webhook event(s) failed and are unresolved.", 'details' => ['unresolved_webhook_failures' => $open]];
        }

        return ['status' => self::HEALTHY, 'message' => 'Stripe is configured and no webhook events are failing.'];
    }
}
