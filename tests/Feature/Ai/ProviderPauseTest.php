<?php

use App\Enums\Ai\AiProviderCredentialStatus;
use App\Exceptions\ProviderPausedException;
use App\Models\Ai\AiProviderCredential;
use App\Models\Ai\AiProviderPause;
use App\Models\Ai\ModelCatalog;
use App\Models\Ai\ModelRoute;
use App\Models\User;
use App\Services\Ai\ByokProviderRegistrar;
use App\Services\Ai\ModelCatalogResolver;
use App\Services\Ai\Operations\ModelHealthReport;
use App\Services\Ai\ProviderPauses;
use App\Services\Ai\WorkspaceModelView;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\Passport;

function pauseProvider(string $provider, string $reason = 'Key leaked'): AiProviderPause
{
    return AiProviderPause::query()->create(['provider' => $provider, 'reason' => $reason, 'paused_at' => now()]);
}

beforeEach(function () {
    config(['ai.providers.openai.key' => 'platform-key', 'ai.providers.anthropic.key' => 'platform-key']);

    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
});

it('stops the platform using a paused provider, and only until it is resumed', function () {
    expect(ModelCatalogResolver::providerIsConfigured('openai'))->toBeTrue();

    $pause = pauseProvider('openai');

    expect(ModelCatalogResolver::providerIsConfigured('openai'))->toBeFalse()
        ->and(ModelCatalogResolver::providerIsConfigured('openai', ignoringPause: true))->toBeTrue()
        ->and(ModelCatalogResolver::providerIsConfigured('anthropic'))->toBeTrue()
        ->and(app(ProviderPauses::class)->isPaused('openai'))->toBeTrue();

    $pause->delete();

    expect(ModelCatalogResolver::providerIsConfigured('openai'))->toBeTrue();
});

it('drops a paused hop from a call\'s chain and keeps the others', function () {
    pauseProvider('openai');

    [$chain] = app(ByokProviderRegistrar::class)->apply(['openai' => 'gpt-x', 'anthropic' => 'claude-x'], null, $this->workspace->id, $this->owner->id);

    expect($chain)->toBe(['anthropic' => 'claude-x']);
});

it('refuses a call whose every platform hop is paused, instead of using the paused key', function () {
    pauseProvider('openai');

    expect(fn () => app(ByokProviderRegistrar::class)->apply(['openai' => 'gpt-x'], null, $this->workspace->id, $this->owner->id))
        ->toThrow(ProviderPausedException::class, 'temporarily unavailable');

    expect(fn () => app(ByokProviderRegistrar::class)->apply('openai', null, $this->workspace->id, $this->owner->id))
        ->toThrow(ProviderPausedException::class);
});

it('leaves a workspace\'s own key for a paused provider working', function () {
    pauseProvider('openai');
    AiProviderCredential::factory()->create([
        'workspace_id' => $this->workspace->id, 'execution_provider' => 'openai', 'validation_status' => AiProviderCredentialStatus::Valid,
    ]);

    [$chain] = app(ByokProviderRegistrar::class)->apply(['openai' => 'gpt-x'], null, $this->workspace->id, $this->owner->id);

    expect(array_keys($chain))->toHaveCount(1)
        ->and(ByokProviderRegistrar::isByok(array_key_first($chain)))->toBeTrue();
});

it('shows a model on a paused provider as unavailable in the picker', function () {
    $model = ModelCatalog::factory()->create(['slug' => 'gpt-x']);
    ModelRoute::factory()->forCatalog($model)->create(['execution_provider' => 'openai', 'execution_model_id' => 'gpt-x']);

    expect(WorkspaceModelView::platform()->describe($model->load(['routes', 'minPlan']))['is_available'])->toBeTrue();

    pauseProvider('openai');

    expect(WorkspaceModelView::platform()->describe($model->load(['routes', 'minPlan']))['is_available'])->toBeFalse();
});

it('answers a real request for a paused model with a 503 and a clear message', function () {
    $model = ModelCatalog::factory()->create(['slug' => 'gpt-4o']);
    ModelRoute::factory()->forCatalog($model)->create(['execution_provider' => 'openai', 'execution_model_id' => 'gpt-4o']);
    pauseProvider('openai');
    Passport::actingAs($this->owner);

    $this->postJson("/api/v1/workspaces/{$this->workspace->id}/model-catalog/gpt-4o/try", ['prompt' => 'Hello'])
        ->assertStatus(503)
        ->assertJsonPath('message', 'This model is temporarily unavailable. Try again later, or pick another model.');
});

it('reports a paused provider once, and a model that cannot run because of it as a warning', function () {
    config(['ai.default_for_embeddings' => 'anthropic']);
    $model = ModelCatalog::factory()->create(['slug' => 'gpt-x']);
    ModelRoute::factory()->forCatalog($model)->create(['execution_provider' => 'openai', 'execution_model_id' => 'gpt-x']);
    pauseProvider('openai', 'Key leaked');

    $report = app(ModelHealthReport::class)->run();
    $codes = array_column($report['findings'], 'code');
    $paused = collect($report['findings'])->firstWhere('code', 'provider_paused');

    expect($codes)->toContain('provider_paused', 'paused_routes_only')
        ->and($codes)->not->toContain('no_usable_route')
        ->and(array_count_values($codes)['provider_paused'])->toBe(1)
        ->and($paused['message'])->toContain('Key leaked')->toContain('1 active model(s)');
});

it('does not stop every call if the table cannot be read', function () {
    Schema::drop('ai_provider_pauses');

    expect(app(ProviderPauses::class)->isPaused('openai'))->toBeFalse()
        ->and(ModelCatalogResolver::providerIsConfigured('openai'))->toBeTrue();
});
