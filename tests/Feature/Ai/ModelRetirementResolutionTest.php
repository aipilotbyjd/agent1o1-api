<?php

use App\Models\Ai\ModelCatalog;
use App\Models\Ai\ModelRoute;
use App\Services\Ai\ModelCatalogResolver;

function catalogWithRoute(string $slug, string $model, array $attributes = []): ModelCatalog
{
    $catalog = ModelCatalog::factory()->create(['slug' => $slug, ...$attributes]);
    ModelRoute::factory()->forCatalog($catalog)->create(['execution_provider' => 'openai', 'execution_model_id' => $model]);

    return $catalog;
}

it('serves a switched-off model through its replacement', function () {
    catalogWithRoute('gpt-new', 'gpt-new-id');
    catalogWithRoute('gpt-old', 'gpt-old-id', ['is_active' => false, 'replacement_slug' => 'gpt-new']);

    expect(app(ModelCatalogResolver::class)->providerChain('gpt-old'))->toBe(['openai' => 'gpt-new-id']);
});

it('serves a model past its retirement date through its replacement', function () {
    catalogWithRoute('gpt-new', 'gpt-new-id');
    catalogWithRoute('gpt-old', 'gpt-old-id', ['retires_at' => now()->subDay(), 'replacement_slug' => 'gpt-new']);

    expect(app(ModelCatalogResolver::class)->providerChain('gpt-old'))->toBe(['openai' => 'gpt-new-id']);
});

it('keeps serving a model whose retirement is still ahead', function () {
    catalogWithRoute('gpt-new', 'gpt-new-id');
    catalogWithRoute('gpt-old', 'gpt-old-id', ['retires_at' => now()->addMonth(), 'replacement_slug' => 'gpt-new']);

    expect(app(ModelCatalogResolver::class)->providerChain('gpt-old'))->toBe(['openai' => 'gpt-old-id']);
});

it('keeps serving a model past its date when nothing replaces it', function () {
    catalogWithRoute('gpt-old', 'gpt-old-id', ['retires_at' => now()->subDay()]);

    expect(app(ModelCatalogResolver::class)->providerChain('gpt-old'))->toBe(['openai' => 'gpt-old-id']);
});

it('refuses a switched-off model that has no replacement', function () {
    catalogWithRoute('gpt-old', 'gpt-old-id', ['is_active' => false]);

    expect(fn () => app(ModelCatalogResolver::class)->providerChain('gpt-old'))->toThrow(RuntimeException::class);
});

it('follows a chain of replacements a few hops and stops on a cycle', function () {
    catalogWithRoute('c', 'c-id');
    catalogWithRoute('b', 'b-id', ['is_active' => false, 'replacement_slug' => 'c']);
    catalogWithRoute('a', 'a-id', ['is_active' => false, 'replacement_slug' => 'b']);

    expect(app(ModelCatalogResolver::class)->providerChain('a'))->toBe(['openai' => 'c-id']);

    catalogWithRoute('x', 'x-id', ['is_active' => false, 'replacement_slug' => 'y']);
    catalogWithRoute('y', 'y-id', ['is_active' => false, 'replacement_slug' => 'x']);

    expect(fn () => app(ModelCatalogResolver::class)->providerChain('x'))->toThrow(RuntimeException::class);
});

it('refuses a model whose replacement is itself unavailable', function () {
    catalogWithRoute('gpt-new', 'gpt-new-id', ['is_active' => false]);
    catalogWithRoute('gpt-old', 'gpt-old-id', ['is_active' => false, 'replacement_slug' => 'gpt-new']);

    expect(fn () => app(ModelCatalogResolver::class)->providerChain('gpt-old'))->toThrow(RuntimeException::class);
});
