<?php

namespace App\Services\SuperAdmin\Pricing;

use App\Models\Billing\Plan;

/**
 * The fields of a plan a super admin can see change, for the before/after
 * halves of a change log entry.
 */
class PlanSnapshot
{
    /**
     * @var list<string>
     */
    public const array FIELDS = [
        'name', 'description', 'credits_monthly', 'limits', 'features', 'trial_days', 'badge',
        'is_public', 'is_active', 'enabled_intervals', 'sort_order',
        'price_monthly', 'price_quarterly', 'price_yearly', 'price_lifetime',
        'stripe_product_id', 'stripe_price_id_monthly', 'stripe_price_id_quarterly',
        'stripe_price_id_yearly', 'stripe_price_id_lifetime',
    ];

    /**
     * @param  list<string>|null  $only
     * @return array<string, mixed>
     */
    public static function of(Plan $plan, ?array $only = null): array
    {
        $snapshot = [];

        foreach ($only ?? self::FIELDS as $field) {
            $snapshot[$field] = $plan->getAttribute($field);
        }

        return $snapshot;
    }
}
