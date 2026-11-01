<?php

namespace App\Models\Billing;

use App\Enums\Billing\BillingInterval;
use App\Enums\Billing\Feature;
use App\Enums\Billing\PlanLimit;
use Database\Factories\Billing\PlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'description', 'price_monthly', 'price_quarterly', 'price_yearly', 'price_lifetime', 'credits_monthly', 'limits', 'features', 'stripe_product_id', 'stripe_price_id_monthly', 'stripe_price_id_quarterly', 'stripe_price_id_yearly', 'stripe_price_id_lifetime', 'enabled_intervals', 'trial_days', 'is_active', 'archived_at', 'is_public', 'badge', 'updated_by', 'sort_order'])]
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'limits' => 'array',
            'features' => 'array',
            'enabled_intervals' => 'array',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Subscriptions that are paying or in trial, as opposed to every row ever.
     *
     * @return HasMany<Subscription, $this>
     */
    public function subscribers(): HasMany
    {
        return $this->hasMany(Subscription::class)->whereIn('stripe_status', ['active', 'trialing']);
    }

    /**
     * @return HasMany<PlanGrant, $this>
     */
    public function activeGrants(): HasMany
    {
        return $this->hasMany(PlanGrant::class)->active();
    }

    /**
     * @return HasMany<PlanGrant, $this>
     */
    public function planGrants(): HasMany
    {
        return $this->hasMany(PlanGrant::class);
    }

    /**
     * @return HasMany<PlanPriceHistory, $this>
     */
    public function priceHistory(): HasMany
    {
        return $this->hasMany(PlanPriceHistory::class);
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * The plan an unsubscribed (or lapsed) workspace falls back to, per
     * `config('billing.default_plan')`. Returns `null` when that slug isn't
     * seeded or is inactive: callers treat that as "no plan", i.e. an
     * unlimited allowance, so a half-seeded install fails open.
     */
    public static function default(): ?self
    {
        $slug = config('billing.default_plan');

        if ($slug === null) {
            return null;
        }

        return self::query()->where('slug', $slug)->where('is_active', true)->first();
    }

    public function creditsMonthly(): int
    {
        return $this->credits_monthly;
    }

    public function hasFeature(Feature $feature): bool
    {
        return (bool) ($this->features[$feature->value] ?? false);
    }

    /**
     * The cap this plan puts on a resource, or `null` for unlimited.
     *
     * A missing key reads as unlimited rather than zero, deliberately: adding
     * a `PlanLimit` case must not retroactively cap every already-seeded plan
     * at nothing. A negative value (the seeder's `-1`) means the same thing
     * explicitly, so a plan can declare "unlimited" rather than omit the key.
     */
    public function limit(PlanLimit $limit): ?int
    {
        $value = $this->limits[$limit->value] ?? -1;

        return $value < 0 ? null : (int) $value;
    }

    /**
     * The fewest minutes between two checks of an app or URL trigger on this
     * plan (`min_poll_interval_minutes`; 1 when the plan sets none). Instant
     * delivery is not held to it.
     */
    public function minPollIntervalMinutes(): int
    {
        return max(1, (int) ($this->limits['min_poll_interval_minutes'] ?? 1));
    }

    /**
     * Keys in `limits` that bound usage and cost rather than how many of
     * something a workspace may own. The admin plan editor accepts these
     * alongside the `PlanLimit` keys; the dollar caps take decimals.
     *
     * @var list<string>
     */
    public const array USAGE_LIMIT_KEYS = [
        'trial_credits',
        'max_run_credits',
        'max_concurrent_runs',
        'credit_rollover_percent',
        'ai_cost_daily_usd',
        'ai_cost_weekly_usd',
    ];

    /**
     * @var list<string>
     */
    public const array DOLLAR_LIMIT_KEYS = ['ai_cost_daily_usd', 'ai_cost_weekly_usd'];

    /**
     * Credits a trialing subscription gets before its first payment. Falls
     * back to the full monthly allowance when the plan does not set one, so
     * a plan row that predates this key keeps behaving as it did.
     */
    public function trialCredits(): int
    {
        $value = $this->limits['trial_credits'] ?? null;

        return is_numeric($value) && $value >= 0 ? (int) $value : $this->creditsMonthly();
    }

    /**
     * The most one run or agent turn may charge before it is stopped.
     * `null` = no cap.
     */
    public function maxRunCredits(): ?int
    {
        return $this->positiveIntLimit('max_run_credits');
    }

    /**
     * How many runs and agent turns may be in flight at once. `null` = no cap.
     */
    public function maxConcurrentRuns(): ?int
    {
        return $this->positiveIntLimit('max_concurrent_runs');
    }

    /**
     * Model cost (USD, on platform keys) a workspace may spend per UTC day.
     */
    public function aiCostDailyUsd(): ?float
    {
        return $this->positiveFloatLimit('ai_cost_daily_usd');
    }

    /**
     * Model cost (USD, on platform keys) a workspace may spend in 7 days.
     */
    public function aiCostWeeklyUsd(): ?float
    {
        return $this->positiveFloatLimit('ai_cost_weekly_usd');
    }

    /**
     * Share of unused allowance (0-100) carried into the next period.
     */
    public function creditRolloverPercent(): int
    {
        return min(100, max(0, (int) ($this->limits['credit_rollover_percent'] ?? 0)));
    }

    private function positiveIntLimit(string $key): ?int
    {
        $value = $this->limits[$key] ?? null;

        return is_numeric($value) && $value > 0 ? (int) $value : null;
    }

    private function positiveFloatLimit(string $key): ?float
    {
        $value = $this->limits[$key] ?? null;

        return is_numeric($value) && $value > 0 ? (float) $value : null;
    }

    public function stripePriceId(BillingInterval $interval): ?string
    {
        return $this->{$interval->stripePriceColumn()};
    }

    /**
     * The plan selling `$stripePriceId` on any interval: the reverse of
     * `stripePriceId()`, for when Stripe hands us a price and we have to work
     * out which plan it belongs to.
     *
     * Every interval is searched rather than an enumerated few. A lookup that
     * covered only some of them silently resolved no plan for the rest, which
     * left a quarterly subscriber's usage period sized from the default plan
     * instead of the one they paid for.
     *
     * Deliberately not filtered to `is_active`: withdrawing a plan from sale
     * never revokes what someone already bought, so a subscription on a
     * deactivated plan must still resolve to it.
     *
     * A price an admin has since replaced is no longer in any `stripe_price_id_*`
     * column but its subscribers are still paying it, so `plan_price_history`
     * is consulted as well. Without that, a price change would orphan every
     * existing subscriber on the old price.
     */
    public static function findByStripePriceId(string $stripePriceId): ?self
    {
        $current = self::query()
            ->where(function (Builder $query) use ($stripePriceId): void {
                foreach (BillingInterval::cases() as $interval) {
                    $query->orWhere($interval->stripePriceColumn(), $stripePriceId);
                }
            })
            ->first();

        if ($current !== null) {
            return $current;
        }

        return PlanPriceHistory::query()
            ->where('stripe_price_id', $stripePriceId)
            ->first()
            ?->plan;
    }

    public function priceFor(BillingInterval $interval): int
    {
        return match ($interval) {
            BillingInterval::Monthly => $this->price_monthly,
            BillingInterval::Quarterly => $this->price_quarterly,
            BillingInterval::Yearly => $this->price_yearly,
            BillingInterval::Lifetime => $this->price_lifetime,
        };
    }

    /**
     * The intervals this plan can actually be bought on right now. Lets the
     * billing screen render exactly the options that will succeed, instead of
     * offering one that 422s on submit.
     *
     * @return list<BillingInterval>
     */
    public function availableIntervals(): array
    {
        return array_values(array_filter(
            BillingInterval::cases(),
            fn (BillingInterval $interval): bool => $this->isSoldOn($interval),
        ));
    }

    /**
     * Four independent things must hold for a purchase to be allowed, and any
     * one of them is a deliberate off switch:
     *
     * 1. the plan itself is active,
     * 2. the interval isn't withdrawn estate-wide (`config('billing.intervals')`),
     * 3. this plan enables the interval (`enabled_intervals`),
     * 4. a Stripe price is configured for it.
     *
     * This governs *selling* only. Entitlement is read from the subscription
     * or plan grant a workspace already holds, so withdrawing an interval
     * (or deactivating the plan outright) never revokes what someone already
     * paid for; it only stops new purchases.
     */
    public function isSoldOn(BillingInterval $interval): bool
    {
        return $this->is_active
            && $interval->isEnabledGlobally()
            && $this->enablesInterval($interval)
            && $this->stripePriceId($interval) !== null;
    }

    /**
     * A null `enabled_intervals` means "all of them": rows predating the
     * column keep their original behaviour rather than becoming unsellable.
     */
    public function enablesInterval(BillingInterval $interval): bool
    {
        if ($this->enabled_intervals === null) {
            return true;
        }

        return in_array($interval->value, $this->enabled_intervals, strict: true);
    }

    /**
     * Why `$interval` can't be bought, phrased for the API's 422. Null when it
     * can be. Distinguishes "withdrawn" from "never configured" so an operator
     * reading the error knows whether to flip a switch or add a Stripe price.
     */
    public function unavailableReasonFor(BillingInterval $interval): ?string
    {
        return match (true) {
            ! $this->is_active => "Plan [{$this->slug}] is not available.",
            ! $interval->isEnabledGlobally() => "The [{$interval->value}] billing interval is not currently offered.",
            ! $this->enablesInterval($interval) => "Plan [{$this->slug}] is not sold on the [{$interval->value}] interval.",
            $this->stripePriceId($interval) === null => "Plan [{$this->slug}] has no Stripe price configured for [{$interval->value}].",
            default => null,
        };
    }
}
