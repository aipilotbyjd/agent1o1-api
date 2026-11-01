<?php

namespace App\Services\Ai\Operations;

use App\Enums\Billing\BillingInterval;
use App\Models\Ai\ModelCatalog;
use App\Models\Ai\ModelRoute;
use App\Models\Billing\Plan;
use App\Services\Ai\ModelCatalogResolver;
use App\Services\Ai\ModelPrices;
use App\Services\Billing\CreditMeter;

/**
 * Whether the credits a model's messages cost are worth more than what the
 * model costs you. For each active model that the platform pays for, it
 * prices one typical chat message (the same message the model picker's cost
 * hint uses) and sets what the provider charges for it against what a credit
 * earns on each plan and billing interval.
 *
 * A credit earns different amounts on different plans, and least on a
 * lifetime plan, which is paid once for credits that keep coming: so the
 * lifetime price is spread over an assumed number of months. The result is
 * about provider cost only; it ignores compute, storage and everything else
 * that is not a model call.
 */
class ModelMargins
{
    /** A margin under this share of revenue is called thin. */
    public const float THIN_MARGIN = 0.2;

    public function __construct(
        private readonly ModelPrices $prices,
        private readonly CreditMeter $meter,
    ) {}

    /**
     * @return array{credit_value_usd: float, lifetime_months: int, tokens: array{prompt: int, completion: int}, plans: list<array{plan: string, interval: string, usd_per_credit: float}>, models: list<array<string, mixed>>}
     */
    public function report(int $lifetimeMonths = 36): array
    {
        $plans = $this->earnings($lifetimeMonths);
        $tokens = $this->meter->typicalMessageTokens();

        $models = ModelCatalog::query()
            ->with(['routes' => fn ($query) => $query->where('is_enabled', true)->orderBy('priority')])
            ->where('is_active', true)
            ->orderBy('slug')
            ->get()
            ->map(fn (ModelCatalog $model): array => $this->forModel($model, $plans, $tokens))
            ->values()
            ->all();

        return [
            'credit_value_usd' => (float) config('billing.credit_value_usd'),
            'lifetime_months' => $lifetimeMonths,
            'tokens' => $tokens,
            'plans' => $plans,
            'models' => $models,
        ];
    }

    /**
     * What one credit earns, per paid plan and interval that is on sale.
     *
     * @return list<array{plan: string, interval: string, usd_per_credit: float}>
     */
    private function earnings(int $lifetimeMonths): array
    {
        $earnings = [];

        foreach (Plan::query()->where('is_active', true)->orderBy('sort_order')->get() as $plan) {
            if ($plan->creditsMonthly() <= 0) {
                continue;
            }

            foreach ($plan->availableIntervals() as $interval) {
                $cents = $plan->priceFor($interval);

                if ($cents <= 0) {
                    continue;
                }

                $months = match ($interval) {
                    BillingInterval::Monthly => 1,
                    BillingInterval::Quarterly => 3,
                    BillingInterval::Yearly => 12,
                    BillingInterval::Lifetime => $lifetimeMonths,
                };

                $earnings[] = [
                    'plan' => $plan->slug,
                    'interval' => $interval->value,
                    'usd_per_credit' => round($cents / 100 / ($plan->creditsMonthly() * $months), 6),
                ];
            }
        }

        return $earnings;
    }

    /**
     * @param  list<array{plan: string, interval: string, usd_per_credit: float}>  $earnings
     * @param  array{prompt: int, completion: int}  $tokens
     * @return array<string, mixed>
     */
    private function forModel(ModelCatalog $model, array $earnings, array $tokens): array
    {
        /** @var ModelRoute|null $route */
        $route = $model->routes->first(fn (ModelRoute $route): bool => ModelCatalogResolver::providerIsConfigured($route->execution_provider));

        $base = ['slug' => $model->slug, 'display_name' => $model->display_name, 'is_internal' => $model->is_internal];

        if ($route === null) {
            return [...$base, 'status' => 'not_paid_by_platform', 'route' => null, 'credits_per_message' => null, 'provider_cost_usd' => null, 'plans' => [], 'worst' => null];
        }

        $price = $this->prices->for($route->execution_provider, $route->execution_model_id);
        $credits = $this->meter->estimateChatMessage($route->execution_provider, $route->execution_model_id);
        $cost = $price === null ? null : ($tokens['prompt'] / 1_000_000 * $price['input']) + ($tokens['completion'] / 1_000_000 * $price['output']);

        $plans = $cost === null ? [] : array_map(function (array $earning) use ($credits, $cost): array {
            $revenue = $credits * $earning['usd_per_credit'];

            return [
                'plan' => $earning['plan'],
                'interval' => $earning['interval'],
                'revenue_usd' => round($revenue, 6),
                'margin_usd' => round($revenue - $cost, 6),
                'margin_pct' => $revenue > 0 ? round(($revenue - $cost) / $revenue, 4) : null,
            ];
        }, $earnings);

        $worst = $plans === [] ? null : collect($plans)->sortBy('margin_usd')->first();

        return [
            ...$base,
            'status' => match (true) {
                $price === null => 'unpriced',
                $worst === null => 'no_paid_plan',
                $worst['margin_usd'] < 0 => 'loss',
                ($worst['margin_pct'] ?? 0) < self::THIN_MARGIN => 'thin',
                default => 'ok',
            },
            'route' => "{$route->execution_provider}:{$route->execution_model_id}",
            'credits_per_message' => $credits,
            'provider_cost_usd' => $cost === null ? null : round($cost, 6),
            'plans' => $plans,
            'worst' => $worst,
        ];
    }
}
