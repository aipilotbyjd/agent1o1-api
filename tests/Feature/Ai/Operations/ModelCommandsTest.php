<?php

use App\Jobs\Workflows\ProcessWorkflowBuilderMessageJob;
use App\Models\Ai\ModelCatalog;
use App\Models\Ai\ModelPrice;
use App\Models\Ai\ModelRoute;
use App\Models\SuperAdmin\SuperAdminAuditLog;

beforeEach(function () {
    config([
        'billing.model_prices' => [],
        'ai.providers.openai.key' => 'platform-key',
        'ai.default_for_embeddings' => 'openai',
        'assistant.runtime.catalog' => 'personal-assistant',
    ]);
});

function commandModel(string $slug, array $attributes = [], bool $priced = true): ModelCatalog
{
    $model = ModelCatalog::factory()->create(['slug' => $slug, ...$attributes]);
    ModelRoute::factory()->forCatalog($model)->create(['execution_provider' => 'openai', 'execution_model_id' => "{$slug}-id"]);

    if ($priced) {
        ModelPrice::factory()->create(['provider' => 'openai', 'model_id' => "{$slug}-id"]);
    }

    return $model;
}

function healthyBase(): void
{
    commandModel(ProcessWorkflowBuilderMessageJob::MODEL_CATALOG_SLUG, ['is_internal' => true]);
    commandModel('personal-assistant', ['is_internal' => true]);
}

it('says the setup is healthy when nothing is wrong', function () {
    healthyBase();

    $this->artisan('model-catalog:doctor')->expectsOutputToContain('healthy')->assertSuccessful();
});

it('fails when something is broken', function () {
    healthyBase();
    ModelRoute::factory()->forCatalog(ModelCatalog::factory()->create(['slug' => 'empty']))->disabled()->create();

    $this->artisan('model-catalog:doctor')->expectsOutputToContain('1 error(s)')->assertFailed();
});

it('lets warnings pass unless asked to fail on them', function () {
    healthyBase();
    commandModel('priceless', priced: false);

    $this->artisan('model-catalog:doctor')->expectsOutputToContain('0 error(s), 1 warning(s)')->assertSuccessful();
    $this->artisan('model-catalog:doctor', ['--fail-on-warnings' => true])->assertFailed();
});

it('retires what is due, moves its users, and records that the scheduler did it', function () {
    commandModel('gpt-new');
    commandModel('gpt-old', ['retires_at' => now()->subHour(), 'replacement_slug' => 'gpt-new']);

    $this->artisan('model-catalog:retire-due')->expectsOutputToContain('Retired [gpt-old] in favour of [gpt-new]')->assertSuccessful();

    $log = SuperAdminAuditLog::query()->where('action', 'models.retired')->firstOrFail();

    expect(ModelCatalog::query()->where('slug', 'gpt-old')->value('is_active'))->toBeFalse()
        ->and($log->actor_label)->toBe('scheduler')
        ->and($log->super_admin_id)->toBeNull()
        ->and($log->after)->toMatchArray(['replacement' => 'gpt-new']);
});

it('reports a retirement it could not carry out and fails', function () {
    commandModel('gpt-old', ['retires_at' => now()->subHour(), 'replacement_slug' => 'ghost']);

    $this->artisan('model-catalog:retire-due')->expectsOutputToContain('Could not retire [gpt-old]')->assertFailed();
});

it('does nothing when nothing is due', function () {
    commandModel('gpt-a');

    $this->artisan('model-catalog:retire-due')->assertSuccessful();

    expect(SuperAdminAuditLog::query()->count())->toBe(0);
});

it('imports the older config prices once', function () {
    config(['billing.model_prices' => ['openai:gpt-a' => ['input' => 1, 'output' => 2]]]);

    $this->artisan('model-prices:import-config')->expectsOutputToContain('Imported 1 price(s)')->assertSuccessful();
    $this->artisan('model-prices:import-config')->expectsOutputToContain('Nothing to import')->assertSuccessful();

    expect(ModelPrice::query()->count())->toBe(1);
});
