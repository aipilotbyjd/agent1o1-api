<?php

use App\Models\Agents\AgentMessage;
use App\Models\Runs\NodeRun;
use App\Services\Billing\CreditMeter;

it('costs 1 base credit for a node type with no configured fixed cost and no usage', function () {
    $nodeRun = NodeRun::factory()->make(['type' => 'transform']);

    expect(app(CreditMeter::class)->costForNodeRun($nodeRun))->toBe(1);
});

it('adds run_code\'s configured fixed cost on top of the base credit', function () {
    $nodeRun = NodeRun::factory()->make(['type' => 'run_code']);

    expect(app(CreditMeter::class)->costForNodeRun($nodeRun))->toBe(4);
});

it('sums base, fixed node cost, and token cost together', function () {
    config(['billing.node_costs.run_code' => 3]);

    $nodeRun = NodeRun::factory()->make([
        'type' => 'run_code',
        'usage' => ['prompt_tokens' => 1500, 'completion_tokens' => 600],
    ]);

    // 1 base + 3 fixed + ceil(2100 / 1000) = 3 token credits = 7
    expect(app(CreditMeter::class)->costForNodeRun($nodeRun))->toBe(7);
});

it('honors an arbitrary node_costs override for any node type', function () {
    config(['billing.node_costs.some_custom_node' => 5]);

    $nodeRun = NodeRun::factory()->make(['type' => 'some_custom_node']);

    expect(app(CreditMeter::class)->costForNodeRun($nodeRun))->toBe(6);
});

it('bills a node run\'s AI token cost at the model\'s real dollar price when one is configured', function () {
    config([
        'billing.model_prices.openai:gpt-4o' => ['input' => 2.50, 'output' => 10.00],
        'billing.credit_value_usd' => 0.005,
    ]);

    $nodeRun = NodeRun::factory()->make([
        'type' => 'ask_ai',
        'usage' => [
            'prompt_tokens' => 1_000_000,
            'completion_tokens' => 1_000_000,
            'provider' => 'openai',
            'model' => 'gpt-4o',
        ],
    ]);

    // $ cost = 1M/1M*2.50 + 1M/1M*10.00 = $12.50 -> 12.50/0.005 = 2500 credits.
    expect(app(CreditMeter::class)->costForNodeRun($nodeRun))->toBe(1 + 2500);
});

it('falls back to the flat token ratio when the model has no configured price', function () {
    $nodeRun = NodeRun::factory()->make([
        'type' => 'ask_ai',
        'usage' => [
            'prompt_tokens' => 1500,
            'completion_tokens' => 600,
            'provider' => 'openai',
            'model' => 'some-unpriced-future-model',
        ],
    ]);

    // ceil(2100 / 1000) = 3 fallback credits.
    expect(app(CreditMeter::class)->costForNodeRun($nodeRun))->toBe(1 + 3);
});

it('prices an agent turn as reasoning + tool calls + compute + orchestration fee', function () {
    config([
        'billing.model_prices.openai:gpt-4o-mini' => ['input' => 0.15, 'output' => 0.60],
        'billing.credit_value_usd' => 0.005,
        'billing.orchestration_fee_rate' => 0.08,
    ]);

    $message = AgentMessage::factory()->assistant()->make([
        'usage' => [
            'prompt_tokens' => 1_000_000,
            'completion_tokens' => 500_000,
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'tool_call_count' => 2,
            'duration_seconds' => 90,
        ],
    ]);

    // reasoning: $ cost = 0.15 + 0.30 = $0.45 -> 90 credits.
    // tool calls: 2. compute: ceil(90/60*5) = 8. subtotal = 100.
    // orchestration fee: ceil(100 * 0.08) = 8. total = 108.
    expect(app(CreditMeter::class)->costForAgentMessage($message))->toBe(108);
});

it('floors a near-instant, tokenless agent turn at 1 compute credit plus its orchestration fee', function () {
    config(['billing.orchestration_fee_rate' => 0.08]);

    $message = AgentMessage::factory()->assistant()->make([
        'usage' => ['prompt_tokens' => 0, 'completion_tokens' => 0, 'tool_call_count' => 0, 'duration_seconds' => 0],
    ]);

    // reasoning 0 + tool calls 0 + compute floor 1 = subtotal 1; fee ceil(1*0.08) = 1.
    expect(app(CreditMeter::class)->costForAgentMessage($message))->toBe(2);
});

it('bills no token credits for a call served by the workspace\'s own provider key', function () {
    config(['billing.model_prices.openai:gpt-4o' => ['input' => 2.5, 'output' => 10.0]]);

    $byok = NodeRun::factory()->make([
        'type' => 'ask_ai',
        'usage' => ['prompt_tokens' => 400_000, 'completion_tokens' => 100_000, 'provider' => 'byok-01a1b2c3', 'model' => 'gpt-4o'],
    ]);
    $platform = NodeRun::factory()->make([
        'type' => 'ask_ai',
        'usage' => ['prompt_tokens' => 400_000, 'completion_tokens' => 100_000, 'provider' => 'openai', 'model' => 'gpt-4o'],
    ]);

    $meter = app(CreditMeter::class);

    expect($meter->costForNodeRun($byok))->toBe(1 + (int) config('billing.node_costs.ask_ai', 0))
        ->and($meter->costForNodeRun($platform))->toBeGreaterThan($meter->costForNodeRun($byok));
});

it('still bills tool calls and compute on a chat turn served by the workspace\'s own key', function () {
    config(['billing.orchestration_fee_rate' => 0]);

    $message = AgentMessage::factory()->assistant()->make([
        'usage' => ['prompt_tokens' => 50_000, 'completion_tokens' => 10_000, 'provider' => 'byok-01a1b2c3', 'model' => 'gpt-4o', 'tool_call_count' => 2, 'duration_seconds' => 30],
    ]);

    // 0 token credits + 2 tool calls + ceil(0.5 min × 5) = 3 compute credits
    expect(app(CreditMeter::class)->costForAgentMessage($message))->toBe(5);
});
