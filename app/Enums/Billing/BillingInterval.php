<?php

namespace App\Enums\Billing;

use App\Services\Billing\BillingSettings;

enum BillingInterval: string
{
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Yearly = 'yearly';
    case Lifetime = 'lifetime';

    /**
     * Whether Stripe bills this interval on a repeating cycle. `Lifetime` is
     * a single payment, so it is bought through a `mode=payment` Checkout
     * Session and entitles the workspace via a `PlanGrant` rather than a
     * `Subscription` row: see `CheckoutLifetimePlanAction`.
     */
    public function isRecurring(): bool
    {
        return $this !== self::Lifetime;
    }

    /**
     * Whether this window is on sale estate-wide: the admin-set switch if
     * there is one, else `config('billing.intervals')`. Unknown keys default
     * to enabled so a newly added interval isn't silently unsellable until
     * config catches up.
     */
    public function isEnabledGlobally(): bool
    {
        return app(BillingSettings::class)->intervalEnabled($this);
    }

    /**
     * The `plans` column holding this interval's Stripe price id. Owned by
     * the enum so the mapping has exactly one home: `Plan::stripePriceId()`
     * reads a price out through it and `Plan::findByStripePriceId()` searches
     * every interval through it, which is what stops a newly added interval
     * from being silently missing on one side of that pair.
     */
    public function stripePriceColumn(): string
    {
        return match ($this) {
            self::Monthly => 'stripe_price_id_monthly',
            self::Quarterly => 'stripe_price_id_quarterly',
            self::Yearly => 'stripe_price_id_yearly',
            self::Lifetime => 'stripe_price_id_lifetime',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Monthly',
            self::Quarterly => 'Quarterly',
            self::Yearly => 'Yearly',
            self::Lifetime => 'Lifetime',
        };
    }
}
