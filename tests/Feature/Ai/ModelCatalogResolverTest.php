<?php

use App\Models\Ai\ModelCatalog;
use App\Models\Ai\ModelRoute;
use App\Services\Ai\ModelCatalogResolver;

it('resolves enabled routes ordered by priority into a provider chain', function () {
    $catalog = ModelCatalog::factory()->create(['slug' => 'claude-3-5-sonnet']);
    ModelRoute::factory()->forCatalog($catalog)->create([
        'execution_provider' => 'openrouter',
        'execution_model_id' => 'anthropic/claude-3.5-sonnet',
        'priority' => 1,
    ]);
    ModelRoute::factory()->forCatalog($catalog)->create([
        'execution_provider' => 'anthropic',
        'execution_model_id' => 'claude-3-5-sonnet-latest',
        'priority' => 0,
    ]);

    $chain = app(ModelCatalogResolver::class)->providerChain('claude-3-5-sonnet');

    expect($chain)->toBe([
        'anthropic' => 'claude-3-5-sonnet-latest',
        'openrouter' => 'anthropic/claude-3.5-sonnet',
    ]);
});

it('excludes disabled routes from the resolved chain', function () {
    $catalog = ModelCatalog::factory()->create(['slug' => 'gpt-4o']);
    ModelRoute::factory()->forCatalog($catalog)->create(['execution_provider' => 'openai', 'execution_model_id' => 'gpt-4o', 'priority' => 0]);
    ModelRoute::factory()->forCatalog($catalog)->disabled()->create(['execution_provider' => 'openrouter', 'execution_model_id' => 'openai/gpt-4o', 'priority' => 1]);

    $chain = app(ModelCatalogResolver::class)->providerChain('gpt-4o');

    expect($chain)->toBe(['openai' => 'gpt-4o']);
});

it('throws when a catalog entry has no enabled route', function () {
    ModelCatalog::factory()->create(['slug' => 'no-routes']);

    expect(fn () => app(ModelCatalogResolver::class)->providerChain('no-routes'))
        ->toThrow(RuntimeException::class);
});

it('throws for an unknown or inactive catalog slug', function () {
    ModelCatalog::factory()->create(['slug' => 'inactive-model', 'is_active' => false]);

    expect(fn () => app(ModelCatalogResolver::class)->providerChain('inactive-model'))
        ->toThrow(RuntimeException::class);

    expect(fn () => app(ModelCatalogResolver::class)->providerChain('does-not-exist'))
        ->toThrow(RuntimeException::class);
});

it('caches the resolved chain until forget() is called', function () {
    $catalog = ModelCatalog::factory()->create(['slug' => 'cached-model']);
    ModelRoute::factory()->forCatalog($catalog)->create(['execution_provider' => 'openai', 'execution_model_id' => 'gpt-4o', 'priority' => 0]);

    $resolver = app(ModelCatalogResolver::class);

    expect($resolver->providerChain('cached-model'))->toBe(['openai' => 'gpt-4o']);

    ModelRoute::factory()->forCatalog($catalog)->create(['execution_provider' => 'openrouter', 'execution_model_id' => 'openai/gpt-4o', 'priority' => 1]);

    // Still cached: the newly added route isn't reflected yet.
    expect($resolver->providerChain('cached-model'))->toBe(['openai' => 'gpt-4o']);

    $resolver->forget('cached-model');

    expect($resolver->providerChain('cached-model'))->toBe([
        'openai' => 'gpt-4o',
        'openrouter' => 'openai/gpt-4o',
    ]);
});
