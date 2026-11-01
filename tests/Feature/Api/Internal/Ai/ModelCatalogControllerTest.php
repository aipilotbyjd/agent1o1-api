<?php

use App\Models\Ai\AiProviderCredential;
use App\Models\Ai\ModelCatalog;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

it('lists active model catalog entries without exposing execution routes', function () {
    $user = User::factory()->create();
    Passport::actingAs($user);

    $catalog = ModelCatalog::factory()->create([
        'slug' => 'claude-3-5-sonnet',
        'display_name' => 'Claude 3.5 Sonnet',
        'brand' => 'anthropic',
        'capabilities' => ['context_window' => 200000],
    ]);
    ModelCatalog::factory()->create(['is_active' => false]);
    ModelCatalog::factory()->create(['slug' => 'workflow-builder-assistant', 'is_internal' => true]);

    $response = $this->getJson('/api/v1/model-catalog');

    $response->assertOk();
    $response->assertJsonCount(1, 'data.model_catalog');
    $response->assertJsonFragment([
        'id' => $catalog->id,
        'slug' => 'claude-3-5-sonnet',
        'display_name' => 'Claude 3.5 Sonnet',
        'brand' => 'anthropic',
    ]);
    $response->assertJsonMissingPath('data.model_catalog.0.routes');
    $response->assertJsonMissingPath('data.model_catalog.0.execution_provider');
});

it('requires authentication', function () {
    $this->getJson('/api/v1/model-catalog')->assertUnauthorized();
});

it('marks an entry available only when an enabled route\'s provider has an API key', function () {
    Passport::actingAs(User::factory()->create());
    config(['ai.providers.keyed' => ['driver' => 'openai-compatible', 'key' => 'sk-test'], 'ai.providers.keyless' => ['driver' => 'openai-compatible', 'key' => null]]);

    $usable = ModelCatalog::factory()->create(['display_name' => 'A usable']);
    $usable->routes()->create(['execution_provider' => 'keyless', 'execution_model_id' => 'm', 'priority' => 0]);
    $usable->routes()->create(['execution_provider' => 'keyed', 'execution_model_id' => 'm', 'priority' => 1]);

    $unusable = ModelCatalog::factory()->create(['display_name' => 'B unusable']);
    $unusable->routes()->create(['execution_provider' => 'keyless', 'execution_model_id' => 'm', 'priority' => 0]);
    $unusable->routes()->create(['execution_provider' => 'keyed', 'execution_model_id' => 'm', 'priority' => 1, 'is_enabled' => false]);

    $entries = collect($this->getJson('/api/v1/model-catalog')->assertOk()->json('data.model_catalog'))->keyBy('id');

    expect($entries[$usable->id]['is_available'])->toBeTrue();
    expect($entries[$unusable->id]['is_available'])->toBeFalse();
});

it('counts a workspace\'s own provider key towards availability for its members', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $outsider = User::factory()->create();
    config(['ai.providers.openai.key' => null]);

    $entry = ModelCatalog::factory()->create();
    $entry->routes()->create(['execution_provider' => 'openai', 'execution_model_id' => 'gpt-4o', 'priority' => 0]);
    AiProviderCredential::factory()->forWorkspace($workspace)->create();

    Passport::actingAs($owner);
    expect($this->getJson('/api/v1/model-catalog')->json('data.model_catalog.0.is_available'))->toBeFalse()
        ->and($this->getJson("/api/v1/model-catalog?workspace_id={$workspace->id}")->json('data.model_catalog.0.is_available'))->toBeTrue();

    Passport::actingAs($outsider);
    expect($this->getJson("/api/v1/model-catalog?workspace_id={$workspace->id}")->json('data.model_catalog.0.is_available'))->toBeFalse();
});
