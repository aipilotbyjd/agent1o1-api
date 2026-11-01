<?php

use App\Enums\SuperAdmin\SuperAdminRole;
use App\Models\Agents\Agent;
use App\Models\Ai\ModelCatalog;
use App\Models\Ai\ModelRoute;
use App\Models\SuperAdmin\SuperAdminAuditLog;
use App\Models\User;
use App\Services\Ai\ModelCatalogResolver;
use App\Services\Workspaces\WorkspaceService;

const LIFECYCLE_REASON = 'The vendor is withdrawing this model';

function lifecycleApiModel(string $slug, array $attributes = []): ModelCatalog
{
    $model = ModelCatalog::factory()->create(['slug' => $slug, ...$attributes]);
    ModelRoute::factory()->forCatalog($model)->create(['execution_provider' => 'openai', 'execution_model_id' => "{$slug}-id"]);

    return $model;
}

beforeEach(function () {
    config(['ai.providers.openai.key' => 'platform-key']);

    $this->operator = superAdmin(SuperAdminRole::Operator);
    $this->headers = superAdminHeaders($this->operator);
    $this->old = lifecycleApiModel('gpt-old');
    $this->new = lifecycleApiModel('gpt-new');
});

it('announces a retirement', function () {
    $this->withHeaders($this->headers)->postJson(saUrl("models/{$this->old->id}/deprecate"), [
        'reason' => LIFECYCLE_REASON, 'replacement_slug' => 'gpt-new', 'retires_at' => now()->addMonth()->toIso8601String(),
    ])->assertOk()->assertJsonPath('data.status', 'deprecated')->assertJsonPath('data.replacement_slug', 'gpt-new');

    $log = SuperAdminAuditLog::query()->where('action', 'models.deprecated')->firstOrFail();

    expect($log->reason)->toBe(LIFECYCLE_REASON)
        ->and($log->after['replacement_slug'])->toBe('gpt-new');
});

it('refuses a bad announcement', function (array $input, int $status) {
    $this->withHeaders($this->headers)->postJson(saUrl("models/{$this->old->id}/deprecate"), [
        'reason' => LIFECYCLE_REASON, 'replacement_slug' => 'gpt-new', 'retires_at' => now()->addMonth()->toIso8601String(), ...$input,
    ])->assertStatus($status);
})->with([
    'a date in the past' => [['retires_at' => '2020-01-01'], 422],
    'no replacement' => [['replacement_slug' => null], 422],
    'a model that does not exist' => [['replacement_slug' => 'ghost'], 409],
    'itself' => [['replacement_slug' => 'gpt-old'], 409],
]);

it('shows what retiring a model would move before doing it', function () {
    $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);
    Agent::factory()->forWorkspace($workspace)->count(2)->create(['model_catalog_id' => $this->old->id]);

    $this->withHeaders($this->headers)->getJson(saUrl("models/{$this->old->id}/retirement-impact"))
        ->assertOk()->assertJsonPath('data.agents', 2);
});

it('retires a model and moves its agents to the replacement', function () {
    $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create(['model_catalog_id' => $this->old->id]);

    $this->withHeaders($this->headers)->postJson(saUrl("models/{$this->old->id}/retire"), ['reason' => LIFECYCLE_REASON, 'replacement_slug' => 'gpt-new'])
        ->assertOk()
        ->assertJsonPath('data', ['replacement' => 'gpt-new', 'agents' => 1, 'assistants' => 0, 'workspaces' => 0]);

    expect($agent->fresh()->model_catalog_id)->toBe($this->new->id)
        ->and($this->old->fresh()->is_active)->toBeFalse()
        ->and(app(ModelCatalogResolver::class)->providerChain('gpt-old'))->toBe(['openai' => 'gpt-new-id']);

    $log = SuperAdminAuditLog::query()->where('action', 'models.retired')->firstOrFail();

    expect($log->after)->toMatchArray(['replacement' => 'gpt-new', 'agents' => 1]);
});

it('will not retire a model in use without a replacement', function () {
    $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);
    Agent::factory()->forWorkspace($workspace)->create(['model_catalog_id' => $this->old->id]);

    $this->withHeaders($this->headers)->postJson(saUrl("models/{$this->old->id}/retire"), ['reason' => LIFECYCLE_REASON])
        ->assertStatus(409);

    expect($this->old->fresh()->is_active)->toBeTrue();
});

it('restores a retired model', function () {
    $this->withHeaders($this->headers)->postJson(saUrl("models/{$this->old->id}/retire"), ['reason' => LIFECYCLE_REASON, 'replacement_slug' => 'gpt-new'])->assertOk();

    $this->withHeaders($this->headers)->postJson(saUrl("models/{$this->old->id}/restore"), ['reason' => 'The vendor extended support'])
        ->assertOk()->assertJsonPath('data.status', 'active');

    expect(SuperAdminAuditLog::query()->where('action', 'models.restored')->exists())->toBeTrue();
});

it('keeps lifecycle changes to an internal model behind a fresh code', function () {
    $internal = lifecycleApiModel('personal-assistant', ['is_internal' => true]);
    lifecycleApiModel('personal-assistant-2', ['is_internal' => true]);

    $this->withHeaders($this->headers)->postJson(saUrl("models/{$internal->id}/retire"), ['reason' => LIFECYCLE_REASON, 'replacement_slug' => 'personal-assistant-2'])
        ->assertUnprocessable()->assertJsonValidationErrors('totp_code');

    $this->withHeaders($this->headers)
        ->postJson(saUrl("models/{$internal->id}/retire"), stepUp($this->operator, ['replacement_slug' => 'personal-assistant-2']))
        ->assertOk();
});

it('keeps lifecycle changes to operators and owners', function (SuperAdminRole $role) {
    $this->withHeaders(superAdminHeaders(superAdmin($role)))
        ->postJson(saUrl("models/{$this->old->id}/retire"), ['reason' => LIFECYCLE_REASON, 'replacement_slug' => 'gpt-new'])
        ->assertForbidden();
})->with([[SuperAdminRole::Finance], [SuperAdminRole::Support], [SuperAdminRole::Viewer]]);
