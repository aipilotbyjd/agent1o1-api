<?php

use App\Ai\Agents\AdHocPromptAgent;
use App\Enums\SuperAdmin\SuperAdminRole;
use App\Models\Ai\ModelCatalog;
use App\Models\Ai\ModelRoute;
use App\Models\SuperAdmin\SuperAdminAuditLog;
use App\Services\Ai\ModelCatalogResolver;
use Database\Factories\Ai\AiEndpointFactory;

const ROUTE_REASON = 'Routing around a provider outage';

beforeEach(function () {
    config(['ai.providers.openai.key' => 'platform-key', 'ai.providers.anthropic.key' => 'platform-key', 'ai.providers.mistral.key' => null]);

    $this->operator = superAdmin(SuperAdminRole::Operator);
    $this->headers = superAdminHeaders($this->operator);
    $this->model = ModelCatalog::factory()->create(['slug' => 'gpt-a']);
    $this->first = ModelRoute::factory()->forCatalog($this->model)->create(['execution_provider' => 'openai', 'execution_model_id' => 'gpt-a', 'priority' => 0]);
});

it('adds a route, switched off unless asked otherwise, after the existing ones', function () {
    $response = $this->withHeaders($this->headers)->postJson(saUrl("models/{$this->model->id}/routes"), [
        'reason' => ROUTE_REASON, 'execution_provider' => 'anthropic', 'execution_model_id' => 'claude-x',
    ])->assertCreated()
        ->assertJsonPath('data.provider', 'anthropic')
        ->assertJsonPath('data.priority', 1)
        ->assertJsonPath('data.is_enabled', false);

    $log = SuperAdminAuditLog::query()->where('action', 'models.route.added')->firstOrFail();

    expect($log->after)->toMatchArray(['provider' => 'anthropic', 'model_id' => 'claude-x', 'is_enabled' => false])
        ->and($log->reason)->toBe(ROUTE_REASON)
        ->and($response->json('data.id'))->not->toBeNull();
});

it('adds a route that is enabled from the start', function () {
    $this->withHeaders($this->headers)->postJson(saUrl("models/{$this->model->id}/routes"), [
        'reason' => ROUTE_REASON, 'execution_provider' => 'anthropic', 'execution_model_id' => 'claude-x', 'is_enabled' => true, 'priority' => 5,
    ])->assertCreated()->assertJsonPath('data.is_enabled', true)->assertJsonPath('data.priority', 5);
});

it('refuses a duplicate route, an unknown provider and a provider it cannot run', function () {
    $url = saUrl("models/{$this->model->id}/routes");

    $this->withHeaders($this->headers)->postJson($url, ['reason' => ROUTE_REASON, 'execution_provider' => 'openai', 'execution_model_id' => 'gpt-a'])
        ->assertStatus(409);

    $this->withHeaders($this->headers)->postJson($url, ['reason' => ROUTE_REASON, 'execution_provider' => 'made-up', 'execution_model_id' => 'x'])
        ->assertUnprocessable()->assertJsonValidationErrors('execution_provider');

    config(['ai.providers.xkiro.key' => null]);

    $this->withHeaders($this->headers)->postJson($url, ['reason' => ROUTE_REASON, 'execution_provider' => 'xkiro', 'execution_model_id' => 'x', 'is_enabled' => true])
        ->assertStatus(409);

    // Off is fine: it can be prepared before the key arrives.
    $this->withHeaders($this->headers)->postJson($url, ['reason' => ROUTE_REASON, 'execution_provider' => 'xkiro', 'execution_model_id' => 'x'])
        ->assertCreated();
});

it('lets a route be enabled for a provider only workspaces with their own key can use', function () {
    $this->withHeaders($this->headers)->postJson(saUrl("models/{$this->model->id}/routes"), [
        'reason' => ROUTE_REASON, 'execution_provider' => 'mistral', 'execution_model_id' => 'mistral-small', 'is_enabled' => true,
    ])->assertCreated()->assertJsonPath('data.kind', 'own_key_only');
});

it('accepts a route on one of the operator\'s own endpoints', function () {
    AiEndpointFactory::new()->create(['slug' => 'my-vllm']);

    $this->withHeaders($this->headers)->postJson(saUrl("models/{$this->model->id}/routes"), [
        'reason' => ROUTE_REASON, 'execution_provider' => 'my-vllm', 'execution_model_id' => 'llama-3-70b', 'is_enabled' => true,
    ])->assertCreated()->assertJsonPath('data.kind', 'endpoint');
});

it('switches a route on and off, tunes it, and drops the cached chain', function () {
    $second = ModelRoute::factory()->forCatalog($this->model)->disabled()->create(['execution_provider' => 'anthropic', 'execution_model_id' => 'claude-x', 'priority' => 1]);

    expect(app(ModelCatalogResolver::class)->providerChain('gpt-a'))->toBe(['openai' => 'gpt-a']);

    $this->withHeaders($this->headers)->patchJson(saUrl("models/{$this->model->id}/routes/{$second->id}"), ['reason' => ROUTE_REASON, 'is_enabled' => true, 'priority' => 2])
        ->assertOk()->assertJsonPath('data.is_enabled', true);

    expect(app(ModelCatalogResolver::class)->providerChain('gpt-a'))->toBe(['openai' => 'gpt-a', 'anthropic' => 'claude-x']);

    $this->withHeaders($this->headers)->patchJson(saUrl("models/{$this->model->id}/routes/{$second->id}"), ['reason' => ROUTE_REASON, 'is_enabled' => false])->assertOk();

    expect(app(ModelCatalogResolver::class)->providerChain('gpt-a'))->toBe(['openai' => 'gpt-a'])
        ->and(SuperAdminAuditLog::query()->where('action', 'models.route.updated')->count())->toBe(2);
});

it('will not enable a route the platform cannot run', function () {
    config(['ai.providers.xkiro.key' => null]);
    $dead = ModelRoute::factory()->forCatalog($this->model)->disabled()->create(['execution_provider' => 'xkiro', 'execution_model_id' => 'x', 'priority' => 1]);

    $this->withHeaders($this->headers)->patchJson(saUrl("models/{$this->model->id}/routes/{$dead->id}"), ['reason' => ROUTE_REASON, 'is_enabled' => true])
        ->assertStatus(409);
});

it('protects the only enabled route of an active model', function () {
    $this->withHeaders($this->headers)->patchJson(saUrl("models/{$this->model->id}/routes/{$this->first->id}"), ['reason' => ROUTE_REASON, 'is_enabled' => false])
        ->assertStatus(409);

    $this->withHeaders($this->headers)->deleteJson(saUrl("models/{$this->model->id}/routes/{$this->first->id}"), ['reason' => ROUTE_REASON])
        ->assertStatus(409);

    expect($this->first->fresh()->is_enabled)->toBeTrue();
});

it('lets the last route go once the model is switched off', function () {
    $this->model->update(['is_active' => false]);

    $this->withHeaders($this->headers)->patchJson(saUrl("models/{$this->model->id}/routes/{$this->first->id}"), ['reason' => ROUTE_REASON, 'is_enabled' => false])->assertOk();
});

it('removes a route that is not the last one', function () {
    $second = ModelRoute::factory()->forCatalog($this->model)->create(['execution_provider' => 'anthropic', 'execution_model_id' => 'claude-x', 'priority' => 1]);

    $this->withHeaders($this->headers)->deleteJson(saUrl("models/{$this->model->id}/routes/{$second->id}"), ['reason' => ROUTE_REASON])->assertNoContent();

    $log = SuperAdminAuditLog::query()->where('action', 'models.route.removed')->firstOrFail();

    expect(ModelRoute::query()->whereKey($second->id)->exists())->toBeFalse()
        ->and($log->before)->toMatchArray(['provider' => 'anthropic', 'model_id' => 'claude-x']);
});

it('cannot touch a route through a different model', function () {
    $other = ModelCatalog::factory()->create(['slug' => 'other']);

    $this->withHeaders($this->headers)->patchJson(saUrl("models/{$other->id}/routes/{$this->first->id}"), ['reason' => ROUTE_REASON, 'priority' => 9])->assertNotFound();
    $this->withHeaders($this->headers)->deleteJson(saUrl("models/{$other->id}/routes/{$this->first->id}"), ['reason' => ROUTE_REASON])->assertNotFound();
    $this->withHeaders($this->headers)->postJson(saUrl("models/{$other->id}/routes/{$this->first->id}/test"))->assertNotFound();
});

it('reorders the routes, and the first is tried first', function () {
    $second = ModelRoute::factory()->forCatalog($this->model)->create(['execution_provider' => 'anthropic', 'execution_model_id' => 'claude-x', 'priority' => 1]);

    $this->withHeaders($this->headers)->postJson(saUrl("models/{$this->model->id}/routes/reorder"), [
        'reason' => ROUTE_REASON, 'route_ids' => [$second->id, $this->first->id],
    ])->assertOk()->assertJsonPath('data.routes.0.provider', 'anthropic');

    expect(array_key_first(app(ModelCatalogResolver::class)->providerChain('gpt-a')))->toBe('anthropic');

    $log = SuperAdminAuditLog::query()->where('action', 'models.routes.reordered')->firstOrFail();

    expect($log->before['route_ids'])->toBe([$this->first->id, $second->id])
        ->and($log->after['route_ids'])->toBe([$second->id, $this->first->id]);
});

it('refuses a reorder that leaves a route out, repeats one, or names a stranger', function (callable $ids, string $errorKey) {
    $second = ModelRoute::factory()->forCatalog($this->model)->create(['execution_provider' => 'anthropic', 'execution_model_id' => 'claude-x', 'priority' => 1]);
    $stranger = ModelRoute::factory()->create();

    $this->withHeaders($this->headers)->postJson(saUrl("models/{$this->model->id}/routes/reorder"), [
        'reason' => ROUTE_REASON, 'route_ids' => $ids($this->first, $second, $stranger),
    ])->assertUnprocessable()->assertJsonValidationErrors($errorKey);
})->with([
    'one missing' => [fn ($a, $b, $c) => [$a->id], 'route_ids'],
    'a stranger in place of one' => [fn ($a, $b, $c) => [$a->id, $c->id], 'route_ids'],
    'one twice' => [fn ($a, $b, $c) => [$a->id, $a->id], 'route_ids.0'],
]);

it('tests a route and keeps what happened on it', function () {
    AdHocPromptAgent::fake(['ok']);

    $this->withHeaders($this->headers)->postJson(saUrl("models/{$this->model->id}/routes/{$this->first->id}/test"))
        ->assertOk()
        ->assertJsonPath('data.ok', true);

    $route = $this->first->fresh();

    expect($route->last_check_ok)->toBeTrue()
        ->and($route->last_checked_at)->not->toBeNull()
        ->and($route->last_check_latency_ms)->toBeInt()
        ->and(SuperAdminAuditLog::query()->where('action', 'models.route.tested')->exists())->toBeTrue();
});

it('records a route that fails its test without failing the request', function () {
    AdHocPromptAgent::fake([fn () => throw new RuntimeException('401 invalid api key')]);

    $this->withHeaders($this->headers)->postJson(saUrl("models/{$this->model->id}/routes/{$this->first->id}/test"))
        ->assertOk()
        ->assertJsonPath('data.ok', false)
        ->assertJsonPath('message', 'The route did not answer.');

    expect($this->first->fresh())
        ->last_check_ok->toBeFalse()
        ->last_check_message->toContain('401');
});

it('needs a reason for every change to a route', function () {
    $second = ModelRoute::factory()->forCatalog($this->model)->create(['execution_provider' => 'anthropic', 'execution_model_id' => 'claude-x', 'priority' => 1]);

    $this->withHeaders($this->headers)->postJson(saUrl("models/{$this->model->id}/routes"), ['execution_provider' => 'anthropic', 'execution_model_id' => 'c'])->assertUnprocessable()->assertJsonValidationErrors('reason');
    $this->withHeaders($this->headers)->patchJson(saUrl("models/{$this->model->id}/routes/{$second->id}"), ['priority' => 3])->assertUnprocessable()->assertJsonValidationErrors('reason');
    $this->withHeaders($this->headers)->deleteJson(saUrl("models/{$this->model->id}/routes/{$second->id}"))->assertUnprocessable()->assertJsonValidationErrors('reason');
});

it('keeps routes of internal models behind a fresh code', function () {
    $internal = ModelCatalog::factory()->create(['slug' => 'personal-assistant', 'is_internal' => true]);
    $route = ModelRoute::factory()->forCatalog($internal)->create(['execution_provider' => 'openai', 'execution_model_id' => 'x']);
    ModelRoute::factory()->forCatalog($internal)->create(['execution_provider' => 'anthropic', 'execution_model_id' => 'y', 'priority' => 1]);

    $this->withHeaders($this->headers)->patchJson(saUrl("models/{$internal->id}/routes/{$route->id}"), ['reason' => ROUTE_REASON, 'priority' => 5])
        ->assertUnprocessable()->assertJsonValidationErrors('totp_code');

    $this->withHeaders($this->headers)->patchJson(saUrl("models/{$internal->id}/routes/{$route->id}"), stepUp($this->operator, ['priority' => 5]))->assertOk();
});

it('keeps route changes to operators and owners', function (SuperAdminRole $role) {
    $admin = superAdmin($role);

    $this->withHeaders(superAdminHeaders($admin))
        ->postJson(saUrl("models/{$this->model->id}/routes/{$this->first->id}/test"))
        ->assertForbidden();
})->with([[SuperAdminRole::Finance], [SuperAdminRole::Support], [SuperAdminRole::Viewer]]);
