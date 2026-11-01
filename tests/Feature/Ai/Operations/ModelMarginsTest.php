<?php

use App\Models\Ai\ModelCatalog;
use App\Models\Ai\ModelPrice;
use App\Models\Ai\ModelRoute;
use App\Models\Billing\Plan;
use App\Services\Ai\Operations\ModelMargins;

/**
 * @return array<string, mixed>|null
 */
function marginFor(string $slug): ?array
{
    return collect(app(ModelMargins::class)->report()['models'])->firstWhere('slug', $slug);
}

function pricedModel(string $slug, float $input, float $output, string $provider = 'openai'): ModelCatalog
{
    $model = ModelCatalog::factory()->create(['slug' => $slug]);
    ModelRoute::factory()->forCatalog($model)->create(['execution_provider' => $provider, 'execution_model_id' => "{$slug}-id"]);
    ModelPrice::factory()->priced($input, $output)->create(['provider' => $provider, 'model_id' => "{$slug}-id"]);

    return $model;
}

beforeEach(function () {
    config([
        'billing.model_prices' => [],
        'billing.credit_value_usd' => 0.005,
        'ai.providers.openai.key' => 'platform-key',
    ]);

    // $29 a month for 5,000 credits is 0.58 cents a credit.
    Plan::factory()->create(['slug' => 'pro', 'price_monthly' => 2900, 'credits_monthly' => 5000, 'enabled_intervals' => ['monthly'], 'sort_order' => 1]);
});

it('works out what a credit earns on each plan and interval that is on sale', function () {
    Plan::factory()->create([
        'slug' => 'forever', 'price_monthly' => 0, 'price_quarterly' => 0, 'price_yearly' => 0, 'price_lifetime' => 36000,
        'credits_monthly' => 1000, 'enabled_intervals' => ['lifetime'], 'stripe_price_id_lifetime' => 'price_life', 'sort_order' => 2,
    ]);
    Plan::factory()->create(['slug' => 'free', 'price_monthly' => 0, 'credits_monthly' => 100, 'enabled_intervals' => ['monthly'], 'sort_order' => 3]);
    Plan::factory()->create(['slug' => 'retired', 'is_active' => false]);

    $plans = collect(app(ModelMargins::class)->report(lifetimeMonths: 36)['plans']);

    expect($plans->pluck('plan')->all())->toBe(['pro', 'forever'])
        ->and($plans->firstWhere('plan', 'pro')['usd_per_credit'])->toBe(0.0058)
        // $360 paid once for 1,000 credits a month, over 36 months: a cent a credit.
        ->and($plans->firstWhere('plan', 'forever')['usd_per_credit'])->toBe(0.01);
});

it('spreads a lifetime price over the months you assume', function () {
    Plan::factory()->create([
        'slug' => 'forever', 'price_monthly' => 0, 'price_lifetime' => 36000, 'credits_monthly' => 1000,
        'enabled_intervals' => ['lifetime'], 'stripe_price_id_lifetime' => 'price_life', 'sort_order' => 2,
    ]);

    $at = fn (int $months) => collect(app(ModelMargins::class)->report($months)['plans'])->firstWhere('plan', 'forever')['usd_per_credit'];

    expect($at(12))->toBe(0.03)->and($at(72))->toBe(0.005);
});

it('shows a healthy margin on a cheap model', function () {
    pricedModel('cheap', 0.15, 0.6);

    $margin = marginFor('cheap');

    expect($margin['status'])->toBe('ok')
        ->and($margin['route'])->toBe('openai:cheap-id')
        ->and($margin['credits_per_message'])->toBeInt()
        ->and($margin['provider_cost_usd'])->toBeGreaterThan(0)
        ->and($margin['worst']['margin_usd'])->toBeGreaterThan(0)
        ->and($margin['plans'])->toHaveCount(1);
});

it('flags a model that costs you more than its credits earn', function () {
    // Credits are priced at half a cent each, so a plan that earns only 0.2 cents a credit loses money on every message.
    Plan::query()->delete();
    Plan::factory()->create(['slug' => 'bargain', 'price_monthly' => 1000, 'credits_monthly' => 5000, 'enabled_intervals' => ['monthly']]);
    pricedModel('costly', 1000, 1000);

    $margin = marginFor('costly');

    expect($margin['status'])->toBe('loss')
        ->and($margin['worst']['margin_usd'])->toBeLessThan(0)
        ->and($margin['worst']['plan'])->toBe('bargain');
});

it('calls a margin under a fifth of revenue thin', function () {
    // 0.52 cents a credit against half a cent of cost leaves about a tenth of revenue.
    Plan::query()->delete();
    Plan::factory()->create(['slug' => 'tight', 'price_monthly' => 2600, 'credits_monthly' => 5000, 'enabled_intervals' => ['monthly']]);
    pricedModel('costly', 1000, 1000);

    $margin = marginFor('costly');

    expect($margin['status'])->toBe('thin')
        ->and($margin['worst']['margin_usd'])->toBeGreaterThan(0)
        ->and($margin['worst']['margin_pct'])->toBeLessThan(ModelMargins::THIN_MARGIN);
});

it('judges a model by the plan that earns least per credit', function () {
    Plan::factory()->create(['slug' => 'bargain', 'price_monthly' => 1000, 'credits_monthly' => 5000, 'enabled_intervals' => ['monthly'], 'sort_order' => 2]);
    pricedModel('costly', 1000, 1000);

    $margin = marginFor('costly');

    expect($margin['plans'])->toHaveCount(2)
        ->and($margin['worst']['plan'])->toBe('bargain')
        ->and(collect($margin['plans'])->firstWhere('plan', 'pro')['margin_usd'])->toBeGreaterThan($margin['worst']['margin_usd']);
});

it('reports a model nobody priced as unpriced, with no margin to show', function () {
    $model = ModelCatalog::factory()->create(['slug' => 'mystery']);
    ModelRoute::factory()->forCatalog($model)->create(['execution_provider' => 'openai', 'execution_model_id' => 'mystery-id']);

    $margin = marginFor('mystery');

    expect($margin['status'])->toBe('unpriced')
        ->and($margin['provider_cost_usd'])->toBeNull()
        ->and($margin['worst'])->toBeNull();
});

it('leaves out of the margin a model the platform does not pay for', function () {
    config(['ai.providers.mistral.key' => null]);
    pricedModel('byok-only', 1, 1, provider: 'mistral');

    expect(marginFor('byok-only')['status'])->toBe('not_paid_by_platform');
});

it('uses the first route the platform can actually run', function () {
    config(['ai.providers.mistral.key' => null]);

    $model = ModelCatalog::factory()->create(['slug' => 'two-routes']);
    ModelRoute::factory()->forCatalog($model)->create(['execution_provider' => 'mistral', 'execution_model_id' => 'skipped', 'priority' => 0]);
    ModelRoute::factory()->forCatalog($model)->create(['execution_provider' => 'openai', 'execution_model_id' => 'used', 'priority' => 1]);
    ModelPrice::factory()->priced(0.15, 0.6)->create(['provider' => 'openai', 'model_id' => 'used']);

    expect(marginFor('two-routes')['route'])->toBe('openai:used');
});

it('has no margin to compute when no paid plan is on sale', function () {
    Plan::query()->delete();
    pricedModel('cheap', 0.15, 0.6);

    $margin = marginFor('cheap');

    expect($margin['status'])->toBe('no_paid_plan')
        ->and($margin['provider_cost_usd'])->toBeGreaterThan(0);
});

it('ignores a model that is switched off', function () {
    pricedModel('gone', 1, 1);
    ModelCatalog::query()->where('slug', 'gone')->update(['is_active' => false]);

    expect(marginFor('gone'))->toBeNull();
});
