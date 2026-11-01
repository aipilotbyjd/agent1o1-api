<?php

use App\Exceptions\ModelLifecycleException;
use App\Models\Agents\Agent;
use App\Models\Ai\ModelCatalog;
use App\Models\Ai\ModelRoute;
use App\Models\Ai\WorkspaceModelSettings;
use App\Models\Assistant\Assistant;
use App\Models\User;
use App\Services\Ai\ModelCatalogResolver;
use App\Services\Ai\Operations\ModelLifecycle;
use App\Services\Workspaces\WorkspaceService;

function lifecycleModel(string $slug, array $attributes = [], bool $withRoute = true): ModelCatalog
{
    $model = ModelCatalog::factory()->create(['slug' => $slug, ...$attributes]);

    if ($withRoute) {
        ModelRoute::factory()->forCatalog($model)->create(['execution_provider' => 'openai', 'execution_model_id' => "{$slug}-id"]);
    }

    return $model;
}

beforeEach(function () {
    $this->old = lifecycleModel('gpt-old');
    $this->new = lifecycleModel('gpt-new');
    $this->workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);
});

it('announces a retirement without changing anything else', function () {
    $when = now()->addMonth();

    app(ModelLifecycle::class)->deprecate($this->old, 'gpt-new', $when);

    $old = $this->old->fresh();

    expect($old->is_active)->toBeTrue()
        ->and($old->replacement_slug)->toBe('gpt-new')
        ->and($old->retires_at->toDateString())->toBe($when->toDateString())
        ->and(app(ModelCatalogResolver::class)->providerChain('gpt-old'))->toBe(['openai' => 'gpt-old-id']);
});

it('refuses a retirement date that has passed, and bad replacements', function (callable $arrange, string $message) {
    $arrange($this);

    expect(fn () => app(ModelLifecycle::class)->deprecate($this->old, $this->replacement ?? 'gpt-new', $this->when ?? now()->addMonth()))
        ->toThrow(ModelLifecycleException::class, $message);
})->with([
    'a past date' => [fn ($t) => $t->when = now()->subDay(), 'in the future'],
    'itself' => [fn ($t) => $t->replacement = 'gpt-old', 'cannot replace itself'],
    'a model that does not exist' => [fn ($t) => $t->replacement = 'nope', 'no model [nope]'],
    'a retired model' => [fn ($t) => $t->new->update(['is_active' => false]), 'itself retired'],
    'a model of the other kind' => [fn ($t) => $t->new->update(['is_internal' => true]), 'internal model can only be replaced'],
]);

it('retires a model and moves everything that picked it', function () {
    $agent = Agent::factory()->forWorkspace($this->workspace)->create(['model_catalog_id' => $this->old->id]);
    $assistant = Assistant::factory()->create(['model_catalog_id' => $this->old->id]);
    $other = lifecycleModel('gpt-other');

    $settings = WorkspaceModelSettings::query()->create([
        'workspace_id' => $this->workspace->id,
        'default_model_catalog_id' => $this->old->id,
        'allowed_model_catalog_ids' => [$this->old->id, $other->id],
    ]);

    $summary = app(ModelLifecycle::class)->retire($this->old, 'gpt-new');

    expect($summary)->toBe(['replacement' => 'gpt-new', 'agents' => 1, 'assistants' => 1, 'workspaces' => 1])
        ->and($agent->fresh()->model_catalog_id)->toBe($this->new->id)
        ->and($assistant->fresh()->model_catalog_id)->toBe($this->new->id)
        ->and($settings->fresh()->default_model_catalog_id)->toBe($this->new->id)
        ->and($settings->fresh()->allowed_model_catalog_ids)->toBe([$this->new->id, $other->id]);

    $old = $this->old->fresh();

    expect($old->is_active)->toBeFalse()
        ->and($old->replacement_slug)->toBe('gpt-new')
        ->and($old->retires_at->isPast() || $old->retires_at->isToday())->toBeTrue()
        ->and(app(ModelCatalogResolver::class)->providerChain('gpt-old'))->toBe(['openai' => 'gpt-new-id']);
});

it('does not duplicate the replacement in an allow-list that already has it', function () {
    WorkspaceModelSettings::query()->create([
        'workspace_id' => $this->workspace->id,
        'allowed_model_catalog_ids' => [$this->old->id, $this->new->id],
    ]);

    app(ModelLifecycle::class)->retire($this->old, 'gpt-new');

    expect(WorkspaceModelSettings::query()->sole()->allowed_model_catalog_ids)->toBe([$this->new->id]);
});

it('uses the announced replacement when none is given', function () {
    app(ModelLifecycle::class)->deprecate($this->old, 'gpt-new', now()->addMonth());

    expect(app(ModelLifecycle::class)->retire($this->old->fresh())['replacement'])->toBe('gpt-new');
});

it('will not retire a model that is in use without a replacement', function () {
    Agent::factory()->forWorkspace($this->workspace)->create(['model_catalog_id' => $this->old->id]);

    expect(fn () => app(ModelLifecycle::class)->retire($this->old))
        ->toThrow(ModelLifecycleException::class, '1 agent(s)');

    expect($this->old->fresh()->is_active)->toBeTrue();
});

it('retires a model nobody uses without a replacement', function () {
    $summary = app(ModelLifecycle::class)->retire($this->old);

    expect($summary['replacement'])->toBeNull()
        ->and($this->old->fresh()->is_active)->toBeFalse();
});

it('never retires an internal model without a replacement', function () {
    $internal = lifecycleModel('assistant-core', ['is_internal' => true]);

    expect(fn () => app(ModelLifecycle::class)->retire($internal))
        ->toThrow(ModelLifecycleException::class, 'internal model is used by the platform itself');
});

it('restores a retired model that still has an enabled route', function () {
    app(ModelLifecycle::class)->retire($this->old, 'gpt-new');

    $restored = app(ModelLifecycle::class)->restore($this->old->fresh());

    expect($restored->is_active)->toBeTrue()
        ->and($restored->retires_at)->toBeNull()
        ->and($restored->replacement_slug)->toBeNull()
        ->and(app(ModelCatalogResolver::class)->providerChain('gpt-old'))->toBe(['openai' => 'gpt-old-id']);
});

it('will not restore a model with nothing to run on', function () {
    $bare = lifecycleModel('bare', ['is_active' => false], withRoute: false);

    expect(fn () => app(ModelLifecycle::class)->restore($bare))
        ->toThrow(ModelLifecycleException::class, 'no enabled route');
});

it('retires the models whose date has come, and only those that name a replacement', function () {
    $due = lifecycleModel('due', ['retires_at' => now()->subHour(), 'replacement_slug' => 'gpt-new']);
    $future = lifecycleModel('future', ['retires_at' => now()->addWeek(), 'replacement_slug' => 'gpt-new']);
    $orphan = lifecycleModel('orphan', ['retires_at' => now()->subHour()]);
    $broken = lifecycleModel('broken', ['retires_at' => now()->subHour(), 'replacement_slug' => 'gone']);

    $result = app(ModelLifecycle::class)->retireDue();

    expect(array_keys($result['retired']))->toBe(['due'])
        ->and($result['retired']['due']['replacement'])->toBe('gpt-new')
        ->and(array_keys($result['failed']))->toBe(['broken'])
        ->and($due->fresh()->is_active)->toBeFalse()
        ->and($future->fresh()->is_active)->toBeTrue()
        ->and($orphan->fresh()->is_active)->toBeTrue()
        ->and($broken->fresh()->is_active)->toBeTrue();
});

it('counts what a retirement would move', function () {
    Agent::factory()->forWorkspace($this->workspace)->count(2)->create(['model_catalog_id' => $this->old->id]);

    expect(app(ModelLifecycle::class)->usage($this->old))->toBe(['agents' => 2, 'assistants' => 0, 'workspaces' => 0]);
});
