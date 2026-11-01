<?php

use App\Models\Ai\AiEndpoint;
use App\Models\Ai\ModelCatalog;
use App\Models\Ai\ModelRoute;
use App\Models\User;
use App\Services\Ai\ByokProviderRegistrar;
use App\Services\Ai\ModelCatalogResolver;
use App\Services\Ai\OperatorEndpoints;
use App\Services\Ai\WorkspaceModelView;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\DB;

it('registers an enabled endpoint as a provider the first time it is asked for', function () {
    AiEndpoint::factory()->create(['slug' => 'my-vllm', 'base_url' => 'https://93.184.216.34/v1', 'api_key' => 'secret-key']);

    expect(config('ai.providers.my-vllm'))->toBeNull()
        ->and(ModelCatalogResolver::providerIsConfigured('my-vllm'))->toBeTrue()
        ->and(config('ai.providers.my-vllm'))->toBe([
            'driver' => 'openai-compatible',
            'url' => 'https://93.184.216.34/v1',
            'key' => 'secret-key',
        ]);
});

it('counts an endpoint that takes no key as configured', function () {
    AiEndpoint::factory()->withoutKey()->create(['slug' => 'local-ollama']);

    expect(ModelCatalogResolver::providerIsConfigured('local-ollama'))->toBeTrue()
        ->and(config('ai.providers.local-ollama.key'))->toBe('none');
});

it('does not register a disabled endpoint', function () {
    AiEndpoint::factory()->disabled()->create(['slug' => 'switched-off']);

    expect(ModelCatalogResolver::providerIsConfigured('switched-off'))->toBeFalse();
});

it('does not know a provider nobody defined', function () {
    expect(ModelCatalogResolver::providerIsConfigured('nobody-defined-this'))->toBeFalse();
});

it('registers the endpoints a resolved chain uses', function () {
    AiEndpoint::factory()->create(['slug' => 'my-vllm', 'api_key' => 'k1']);
    $catalog = ModelCatalog::factory()->create(['slug' => 'house-model']);
    ModelRoute::factory()->forCatalog($catalog)->create(['execution_provider' => 'my-vllm', 'execution_model_id' => 'llama-3-70b']);

    $chain = app(ModelCatalogResolver::class)->providerChain('house-model');

    expect($chain)->toBe(['my-vllm' => 'llama-3-70b'])
        ->and(config('ai.providers.my-vllm.key'))->toBe('k1');
});

it('picks up a changed key and a switched-off endpoint without a restart', function () {
    $endpoint = AiEndpoint::factory()->create(['slug' => 'my-vllm', 'api_key' => 'old-key']);
    $registry = app(OperatorEndpoints::class);

    $registry->ensureRegistered('my-vllm');
    expect(config('ai.providers.my-vllm.key'))->toBe('old-key');

    $endpoint->update(['api_key' => 'new-key']);

    // Inside the re-check window the process keeps what it has.
    $registry->ensureRegistered('my-vllm');
    expect(config('ai.providers.my-vllm.key'))->toBe('old-key');

    $this->travel(31)->seconds();
    $registry->ensureRegistered('my-vllm');
    expect(config('ai.providers.my-vllm.key'))->toBe('new-key');

    $endpoint->update(['is_enabled' => false]);
    $this->travel(31)->seconds();
    $registry->ensureRegistered('my-vllm');

    expect(config('ai.providers.my-vllm'))->toBeNull()
        ->and(ModelCatalogResolver::providerIsConfigured('my-vllm'))->toBeFalse();
});

it('can be told to re-read an endpoint changed in this very request', function () {
    $endpoint = AiEndpoint::factory()->create(['slug' => 'my-vllm', 'api_key' => 'old-key']);
    $registry = app(OperatorEndpoints::class);

    $registry->ensureRegistered('my-vllm');
    $endpoint->update(['api_key' => 'new-key']);

    $registry->refresh('my-vllm');
    $registry->ensureRegistered('my-vllm');

    expect(config('ai.providers.my-vllm.key'))->toBe('new-key');
});

it('costs no query for a provider config/ai.php already defines', function () {
    DB::enableQueryLog();

    app(OperatorEndpoints::class)->ensureRegistered('openai');
    app(OperatorEndpoints::class)->ensureRegistered('anthropic');

    expect(DB::getQueryLog())->toBe([]);
});

it('reserves the names of real providers and of BYOK providers', function () {
    AiEndpoint::factory()->create(['slug' => 'my-vllm']);

    $reserved = app(OperatorEndpoints::class)->reservedSlugs();

    expect($reserved)->toContain('openai', 'anthropic', 'fireworks')
        ->and($reserved)->not->toContain('my-vllm')
        ->and(OperatorEndpoints::slugIsByokShaped('byok-123'))->toBeTrue()
        ->and(OperatorEndpoints::slugIsByokShaped('my-vllm'))->toBeFalse();
});

it('keeps an endpoint\'s key out of its serialised form and shows only a hint', function () {
    $endpoint = AiEndpoint::factory()->create(['api_key' => 'sk-abcdefghijklmnop']);

    expect($endpoint->toArray())->not->toHaveKey('api_key')
        ->and($endpoint->keyHint())->toBe('…mnop')
        ->and(AiEndpoint::factory()->withoutKey()->create()->keyHint())->toBeNull();
});

it('leaves no trace of a provider that turned out not to exist', function () {
    app(OperatorEndpoints::class)->ensureRegistered('ghost');

    expect(array_key_exists('ghost', (array) config('ai.providers')))->toBeFalse();

    $endpoint = AiEndpoint::factory()->create(['slug' => 'my-vllm']);
    app(OperatorEndpoints::class)->ensureRegistered('my-vllm');
    $endpoint->update(['is_enabled' => false]);
    $this->travel(31)->seconds();
    app(OperatorEndpoints::class)->ensureRegistered('my-vllm');

    expect(array_key_exists('my-vllm', (array) config('ai.providers')))->toBeFalse();
});

it('keeps an endpoint hop in the chain a workspace call is made with', function () {
    AiEndpoint::factory()->create(['slug' => 'my-vllm']);
    $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);

    [$chain] = app(ByokProviderRegistrar::class)->apply(['my-vllm' => 'llama-3-70b'], null, $workspace->id, $workspace->owner_id);

    expect($chain)->toBe(['my-vllm' => 'llama-3-70b']);
});

it('shows a model served by an operator endpoint as available in the picker', function () {
    AiEndpoint::factory()->create(['slug' => 'my-vllm']);
    $catalog = ModelCatalog::factory()->create(['slug' => 'house-model']);
    ModelRoute::factory()->forCatalog($catalog)->create(['execution_provider' => 'my-vllm', 'execution_model_id' => 'llama-3-70b']);

    $view = WorkspaceModelView::platform()->describe($catalog->load('routes'));

    expect($view['is_available'])->toBeTrue()
        ->and($view['unavailable_reason'])->toBeNull()
        // A customer cannot bring a key for the operator's own server.
        ->and($view['connect_providers'])->toBe([]);
});

it('shows it as unavailable once the endpoint is switched off', function () {
    AiEndpoint::factory()->disabled()->create(['slug' => 'my-vllm']);
    $catalog = ModelCatalog::factory()->create(['slug' => 'house-model']);
    ModelRoute::factory()->forCatalog($catalog)->create(['execution_provider' => 'my-vllm', 'execution_model_id' => 'llama-3-70b']);

    expect(WorkspaceModelView::platform()->describe($catalog->load('routes'))['is_available'])->toBeFalse();
});
