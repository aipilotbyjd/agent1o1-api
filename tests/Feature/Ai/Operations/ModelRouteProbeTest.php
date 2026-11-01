<?php

use App\Ai\Agents\AdHocPromptAgent;
use App\Models\Ai\AiEndpoint;
use App\Models\Ai\ModelPrice;
use App\Services\Ai\Operations\ModelRouteProbe;

beforeEach(fn () => config(['ai.providers.openai.key' => 'platform-key']));

it('reports a route that answered, with its latency, tokens and cost', function () {
    ModelPrice::factory()->priced(10, 20)->create(['provider' => 'openai', 'model_id' => 'gpt-x']);
    AdHocPromptAgent::fake(['ok']);

    $result = app(ModelRouteProbe::class)->probe('openai', 'gpt-x');

    expect($result['ok'])->toBeTrue()
        ->and($result['latency_ms'])->toBeInt()
        ->and($result['message'])->toBe('The route answered.')
        ->and($result['prompt_tokens'])->toBeInt()
        ->and($result['estimated_cost_usd'])->toBeFloat();
});

it('has no cost for a route nobody priced', function () {
    config(['billing.model_prices' => []]);
    AdHocPromptAgent::fake(['ok']);

    expect(app(ModelRouteProbe::class)->probe('openai', 'gpt-unpriced')['estimated_cost_usd'])->toBeNull();
});

it('will not test a provider the platform has no key for', function () {
    config(['ai.providers.mistral.key' => null]);

    $result = app(ModelRouteProbe::class)->probe('mistral', 'mistral-small');

    expect($result['ok'])->toBeFalse()
        ->and($result['message'])->toContain('no key')
        ->and($result['latency_ms'])->toBeNull();
});

it('reports a route that failed instead of throwing', function () {
    AdHocPromptAgent::fake([fn () => throw new RuntimeException('429 Too Many Requests')]);

    $result = app(ModelRouteProbe::class)->probe('openai', 'gpt-x');

    expect($result['ok'])->toBeFalse()
        ->and($result['message'])->toContain('429')
        ->and($result['latency_ms'])->toBeInt();
});

it('can test one of the operator\'s own endpoints', function () {
    AiEndpoint::factory()->create(['slug' => 'my-vllm']);
    AdHocPromptAgent::fake(['ok']);

    expect(app(ModelRouteProbe::class)->probe('my-vllm', 'llama-3-70b')['ok'])->toBeTrue();
});
