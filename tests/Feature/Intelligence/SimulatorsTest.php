<?php

use App\Models\Ai\ModelPrice;
use App\Models\Billing\Plan;
use App\Services\Intelligence\PricingSimulator;
use App\Services\Intelligence\RoutingSimulator;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    config(['billing.margin_floor.payment_fee_rate' => 0.0, 'billing.credit_value_usd' => 0.005, 'billing.overage.credit_value_usd' => 0.01]);
});

function fact(string $workspaceId, string $plan, int $credits, float $cost): void
{
    DB::table('fact_workspace_daily')->insert([
        'date' => today()->toDateString(), 'workspace_id' => $workspaceId, 'plan_slug' => $plan, 'is_paying' => true,
        'credits_used' => $credits, 'revenue_usd' => 0, 'provider_cost_usd' => $cost, 'margin_usd' => 0, 'ai_calls' => 1, 'failed_calls' => 0,
    ]);
}

it('replays real usage against a proposed plan and counts who pays more', function () {
    Plan::factory()->create(['slug' => 'pro', 'credits_monthly' => 20_000, 'price_monthly' => 9900]);
    // Over 30 days: one light customer and one who uses 30,000 credits a month.
    fact('00000000-0000-0000-0000-000000000001', 'pro', 5_000, 20.0);
    fact('00000000-0000-0000-0000-000000000002', 'pro', 30_000, 140.0);

    $result = app(PricingSimulator::class)->simulate(['plans' => ['pro' => ['credits_monthly' => 15_000]]], 30);

    // Current: $99 + ($99 + 10,000 × $0.01) = $298. Proposed: $99 + ($99 + 15,000 × $0.01) = $348.
    expect($result['current']['monthly_revenue_usd'])->toBe(298.0)
        ->and($result['proposed']['monthly_revenue_usd'])->toBe(348.0)
        ->and($result['proposed']['monthly_provider_cost_usd'])->toBe(160.0)
        ->and($result['customers_paying_more'])->toBe(1)
        ->and($result['customers_newly_over_allowance'])->toBe(0)
        ->and($result['margin_floor'][0]['subject'])->toBe('pro');
});

it('prices one model\'s recent tokens on another', function () {
    ModelPrice::factory()->create(['provider' => 'openai', 'model_id' => 'cheap', 'input_per_mtok' => 0.5, 'output_per_mtok' => 1.5]);
    DB::table('fact_usage_daily')->insert([
        'date' => today()->toDateString(), 'workspace_id' => null, 'feature' => 'judges', 'provider' => 'anthropic', 'model' => 'premium', 'is_byok' => false,
        'calls' => 100, 'failed_calls' => 0, 'input_tokens' => 2_000_000, 'cache_read_tokens' => 0, 'output_tokens' => 1_000_000,
        'provider_cost_usd' => 21.0, 'untrusted_cost_usd' => 0,
    ]);

    $result = app(RoutingSimulator::class)->simulate('anthropic', 'premium', 'openai', 'cheap', 'judges', 7);

    expect($result)->toMatchArray([
        'calls' => 100,
        'current_cost_usd' => 21.0,
        'projected_cost_usd' => 2.5,
        'saving_usd' => 18.5,
    ]);
});

it('refuses to price a switch to a model with no price', function () {
    expect(fn () => app(RoutingSimulator::class)->simulate('a', 'b', 'c', 'd'))->toThrow(InvalidArgumentException::class);
});
