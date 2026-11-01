<?php

use App\Enums\SuperAdmin\SuperAdminRole;
use App\Models\Ai\AiEndpoint;
use App\Models\Ai\ModelCatalog;
use App\Models\Ai\ModelPrice;
use App\Models\Ai\ModelRoute;
use App\Models\Billing\Plan;
use App\Models\SuperAdmin\SuperAdminAuditLog;
use App\Services\Ai\ModelCatalogResolver;

beforeEach(function () {
    config(['ai.providers.openai.key' => 'platform-key', 'billing.model_prices' => []]);

    $this->operator = superAdmin(SuperAdminRole::Operator);
    $this->headers = superAdminHeaders($this->operator);
});

/**
 * A model with one enabled route on a provider the platform has a key for.
 *
 * @param  array<string, mixed>  $attributes
 */
function apiModel(string $slug, array $attributes = []): ModelCatalog
{
    $model = ModelCatalog::factory()->create(['slug' => $slug, ...$attributes]);
    ModelRoute::factory()->forCatalog($model)->create(['execution_provider' => 'openai', 'execution_model_id' => "{$slug}-id"]);

    return $model;
}

const MODEL_REASON = 'Adding the new release to the picker';

it('lists the catalog with its routes, to any role', function (SuperAdminRole $role) {
    apiModel('gpt-a');
    ModelPrice::factory()->priced(1, 2)->create(['provider' => 'openai', 'model_id' => 'gpt-a-id']);

    $this->withHeaders(superAdminHeaders(superAdmin($role)))->getJson(saUrl('models'))
        ->assertOk()
        ->assertJsonPath('data.0.slug', 'gpt-a')
        ->assertJsonPath('data.0.status', 'active')
        ->assertJsonPath('data.0.routes.0.provider', 'openai')
        ->assertJsonPath('data.0.routes.0.kind', 'platform')
        ->assertJsonPath('data.0.routes.0.platform_can_run', true)
        ->assertJsonPath('data.0.routes.0.price.input_per_mtok', 1)
        ->assertJsonPath('data.0.routes.0.price.in_table', true);
})->with(array_map(fn (SuperAdminRole $role) => [$role], SuperAdminRole::cases()));

it('never puts a key anywhere in what it returns', function () {
    apiModel('gpt-a');
    AiEndpoint::factory()->create(['slug' => 'my-vllm', 'api_key' => 'sk-supersecretvalue']);

    $body = $this->withHeaders($this->headers)->getJson(saUrl('models'))->getContent();
    $endpoints = $this->withHeaders($this->headers)->getJson(saUrl('models/endpoints'));

    expect($body)->not->toContain('platform-key')
        ->and($endpoints->getContent())->not->toContain('supersecretvalue')
        ->and($endpoints->json('data.0.key_hint'))->toBe('…alue');
});

it('filters the catalog by search, status and kind', function () {
    apiModel('gpt-a', ['display_name' => 'Alpha']);
    apiModel('gpt-b', ['display_name' => 'Beta', 'is_active' => false]);
    apiModel('gpt-c', ['display_name' => 'Gamma', 'retires_at' => now()->addMonth(), 'replacement_slug' => 'gpt-a']);
    apiModel('internal-x', ['is_internal' => true]);

    $slugs = fn (string $query) => array_column($this->withHeaders($this->headers)->getJson(saUrl("models?{$query}"))->json('data'), 'slug');

    expect($slugs('q=alpha'))->toBe(['gpt-a'])
        ->and($slugs('status=draft'))->toBe(['gpt-b'])
        ->and($slugs('status=deprecated'))->toBe(['gpt-c'])
        ->and($slugs('internal=1'))->toBe(['internal-x'])
        ->and($slugs('internal=0'))->toContain('gpt-a', 'gpt-b', 'gpt-c')
        ->and($slugs('internal=0'))->not->toContain('internal-x');

    $this->withHeaders($this->headers)->getJson(saUrl('models?status=imaginary'))->assertUnprocessable();
});

it('shows one model with what a retirement would move', function () {
    $model = apiModel('gpt-a');

    $this->withHeaders($this->headers)->getJson(saUrl("models/{$model->id}"))
        ->assertOk()
        ->assertJsonPath('data.slug', 'gpt-a')
        ->assertJsonPath('data.used_by', ['agents' => 0, 'assistants' => 0, 'workspaces' => 0]);

    $this->withHeaders($this->headers)->getJson(saUrl("models/{$model->id}/retirement-impact"))
        ->assertOk()
        ->assertJsonPath('data.agents', 0);
});

it('creates a model switched off, and records why', function () {
    $this->withHeaders($this->headers)->postJson(saUrl('models'), [
        'reason' => MODEL_REASON,
        'slug' => 'new-model',
        'display_name' => 'New Model',
        'brand' => 'acme',
        'description' => 'A model.',
        'strengths' => ['Speed', 'Cost'],
        'speed' => 'fast',
        'cost_tier' => 1,
        'capabilities' => ['context_window' => 128000, 'tool_use' => true],
    ])
        ->assertCreated()
        ->assertJsonPath('data.slug', 'new-model')
        ->assertJsonPath('data.is_active', false)
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.is_internal', false);

    $log = SuperAdminAuditLog::query()->where('action', 'models.created')->firstOrFail();

    expect($log->reason)->toBe(MODEL_REASON)
        ->and($log->after)->toMatchArray(['slug' => 'new-model']);
});

it('validates a new model', function (array $input, string $error) {
    apiModel('taken');

    $this->withHeaders($this->headers)->postJson(saUrl('models'), [
        'reason' => MODEL_REASON, 'slug' => 'fresh', 'display_name' => 'Fresh', 'brand' => 'acme', ...$input,
    ])->assertUnprocessable()->assertJsonValidationErrors($error);
})->with([
    'a slug already used' => [['slug' => 'taken'], 'slug'],
    'a slug with capitals' => [['slug' => 'Bad Slug'], 'slug'],
    'no display name' => [['display_name' => ''], 'display_name'],
    'an unknown speed' => [['speed' => 'warp'], 'speed'],
    'a cost tier out of range' => [['cost_tier' => 9], 'cost_tier'],
    'too many strengths' => [['strengths' => ['a', 'b', 'c', 'd', 'e', 'f', 'g']], 'strengths'],
    'an unknown capability' => [['capabilities' => ['telepathy' => true]], 'capabilities'],
    'no reason' => [['reason' => ''], 'reason'],
]);

it('will not create an internal model', function () {
    $this->withHeaders($this->headers)->postJson(saUrl('models'), [
        'reason' => MODEL_REASON, 'slug' => 'sneaky', 'display_name' => 'Sneaky', 'brand' => 'acme', 'is_internal' => true,
    ])->assertCreated()->assertJsonPath('data.is_internal', false);
});

it('edits what the picker shows and keeps the slug', function () {
    $model = apiModel('gpt-a');

    $this->withHeaders($this->headers)->patchJson(saUrl("models/{$model->id}"), [
        'reason' => MODEL_REASON, 'display_name' => 'GPT A (new name)', 'cost_tier' => 2, 'slug' => 'renamed',
    ])->assertOk()->assertJsonPath('data.display_name', 'GPT A (new name)')->assertJsonPath('data.slug', 'gpt-a');

    $log = SuperAdminAuditLog::query()->where('action', 'models.updated')->firstOrFail();

    expect($log->after)->toHaveKey('display_name')->and($log->before)->toHaveKey('display_name');
});

it('switches a model on only when it has an enabled route', function () {
    $bare = ModelCatalog::factory()->create(['slug' => 'bare', 'is_active' => false]);
    ModelRoute::factory()->forCatalog($bare)->disabled()->create();

    $this->withHeaders($this->headers)->patchJson(saUrl("models/{$bare->id}"), ['reason' => MODEL_REASON, 'is_active' => true])->assertStatus(409);

    $bare->routes()->first()->update(['is_enabled' => true]);

    $this->withHeaders($this->headers)->patchJson(saUrl("models/{$bare->id}"), ['reason' => MODEL_REASON, 'is_active' => true])
        ->assertOk()->assertJsonPath('data.status', 'active');
});

it('switches a model off, and a request for it then fails like any unavailable model', function () {
    $model = apiModel('gpt-a');

    $this->withHeaders($this->headers)->patchJson(saUrl("models/{$model->id}"), ['reason' => MODEL_REASON, 'is_active' => false])->assertOk();

    expect(fn () => app(ModelCatalogResolver::class)->providerChain('gpt-a'))->toThrow(RuntimeException::class);
});

it('makes a change live straight away by dropping the cached chain', function () {
    $model = apiModel('gpt-a');
    ModelRoute::query()->update(['priority' => 5]);

    expect(app(ModelCatalogResolver::class)->providerChain('gpt-a'))->toBe(['openai' => 'gpt-a-id']);

    $this->withHeaders($this->headers)->postJson(saUrl("models/{$model->id}/routes"), [
        'reason' => MODEL_REASON, 'execution_provider' => 'anthropic', 'execution_model_id' => 'claude-x', 'priority' => 0, 'is_enabled' => true,
    ])->assertCreated();

    config(['ai.providers.anthropic.key' => 'k']);

    expect(array_key_first(app(ModelCatalogResolver::class)->providerChain('gpt-a')))->toBe('anthropic');
});

it('needs a fresh authenticator code to touch an internal model, and not for a public one', function () {
    $internal = apiModel('personal-assistant', ['is_internal' => true]);
    $public = apiModel('gpt-a');

    $this->withHeaders($this->headers)->patchJson(saUrl("models/{$public->id}"), ['reason' => MODEL_REASON, 'display_name' => 'Fine'])->assertOk();

    $this->withHeaders($this->headers)->patchJson(saUrl("models/{$internal->id}"), ['reason' => MODEL_REASON, 'display_name' => 'Renamed'])
        ->assertUnprocessable()->assertJsonValidationErrors('totp_code');

    $this->withHeaders($this->headers)
        ->patchJson(saUrl("models/{$internal->id}"), stepUp($this->operator, ['display_name' => 'Renamed']))
        ->assertOk();

    $this->withHeaders($this->headers)
        ->patchJson(saUrl("models/{$internal->id}"), [...stepUp($this->operator, ['display_name' => 'Again']), 'totp_code' => '000000'])
        ->assertUnprocessable()->assertJsonValidationErrors('totp_code');
});

it('never lets an internal model be switched off', function () {
    $internal = apiModel('personal-assistant', ['is_internal' => true]);

    $this->withHeaders($this->headers)
        ->patchJson(saUrl("models/{$internal->id}"), stepUp($this->operator, ['is_active' => false]))
        ->assertStatus(409);

    expect($internal->fresh()->is_active)->toBeTrue();
});

it('lets only operators and owners change the catalog', function (SuperAdminRole $role, int $status) {
    $model = apiModel('gpt-a');
    $admin = superAdmin($role);

    $this->withHeaders(superAdminHeaders($admin))
        ->patchJson(saUrl("models/{$model->id}"), ['reason' => MODEL_REASON, 'display_name' => 'Renamed'])
        ->assertStatus($status);
})->with([
    'owner' => [SuperAdminRole::Owner, 200],
    'operator' => [SuperAdminRole::Operator, 200],
    'finance' => [SuperAdminRole::Finance, 403],
    'support' => [SuperAdminRole::Support, 403],
    'viewer' => [SuperAdminRole::Viewer, 403],
]);

it('refuses a user token and an unauthenticated caller', function () {
    $this->flushHeaders()->getJson(saUrl('models'))->assertUnauthorized();
});

it('limits a model to a plan, and lifts the limit', function () {
    $pro = Plan::factory()->create(['slug' => 'pro', 'name' => 'Pro']);
    $model = apiModel('gpt-a');

    $this->withHeaders($this->headers)->patchJson(saUrl("models/{$model->id}"), ['reason' => MODEL_REASON, 'min_plan_id' => $pro->id])
        ->assertOk()->assertJsonPath('data.min_plan.slug', 'pro');

    expect($model->fresh()->min_plan_id)->toBe($pro->id)
        ->and(SuperAdminAuditLog::query()->where('action', 'models.updated')->firstOrFail()->after)->toHaveKey('min_plan_id', $pro->id);

    $this->withHeaders($this->headers)->patchJson(saUrl("models/{$model->id}"), ['reason' => MODEL_REASON, 'min_plan_id' => null])
        ->assertOk()->assertJsonPath('data.min_plan', null);

    $this->withHeaders($this->headers)->patchJson(saUrl("models/{$model->id}"), ['reason' => MODEL_REASON, 'min_plan_id' => '01a169d4-4d83-7380-9987-4bd93a3f1481'])
        ->assertUnprocessable()->assertJsonValidationErrors('min_plan_id');
});

it('can create a model that is limited to a plan from the start', function () {
    $pro = Plan::factory()->create(['slug' => 'pro']);

    $this->withHeaders($this->headers)->postJson(saUrl('models'), [
        'reason' => MODEL_REASON, 'slug' => 'premium', 'display_name' => 'Premium', 'brand' => 'acme', 'min_plan_id' => $pro->id,
    ])->assertCreated()->assertJsonPath('data.min_plan.slug', 'pro');
});
