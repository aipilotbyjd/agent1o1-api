<?php

use App\Enums\Ai\AiProviderCredentialStatus;
use App\Jobs\Workflows\ProcessWorkflowBuilderMessageJob;
use App\Models\Ai\AiEndpoint;
use App\Models\Ai\AiProviderCredential;
use App\Models\Ai\ModelCatalog;
use App\Models\Ai\ModelPrice;
use App\Models\Ai\ModelRoute;
use App\Models\User;
use App\Services\Ai\Operations\ModelHealthReport;
use App\Services\Workspaces\WorkspaceService;

/**
 * A model with one priced, enabled route on a provider the platform has a key for.
 *
 * @param  array<string, mixed>  $attributes
 */
function healthyModel(string $slug, array $attributes = [], bool $priced = true): ModelCatalog
{
    $model = ModelCatalog::factory()->create(['slug' => $slug, ...$attributes]);
    ModelRoute::factory()->forCatalog($model)->create(['execution_provider' => 'openai', 'execution_model_id' => "{$slug}-id"]);

    if ($priced) {
        ModelPrice::factory()->create(['provider' => 'openai', 'model_id' => "{$slug}-id"]);
    }

    return $model;
}

/**
 * @param  array<string, mixed>  $report
 * @return array<int, string>
 */
function codes(array $report, ?string $slug = null): array
{
    return collect($report['findings'])
        ->when($slug !== null, fn ($findings) => $findings->filter(fn (array $finding): bool => $finding['subject']['slug'] === $slug))
        ->pluck('code')
        ->sort()
        ->values()
        ->all();
}

beforeEach(function () {
    config([
        'billing.model_prices' => [],
        'ai.providers.openai.key' => 'platform-key',
        'ai.default_for_embeddings' => 'openai',
        'assistant.runtime.catalog' => 'personal-assistant',
    ]);

    healthyModel(ProcessWorkflowBuilderMessageJob::MODEL_CATALOG_SLUG, ['is_internal' => true]);
    healthyModel('personal-assistant', ['is_internal' => true]);
});

it('finds nothing wrong with a healthy setup', function () {
    healthyModel('gpt-ok');

    $report = app(ModelHealthReport::class)->run();

    expect($report['healthy'])->toBeTrue()
        ->and($report['summary'])->toBe(['errors' => 0, 'warnings' => 0, 'info' => 0])
        ->and($report['findings'])->toBe([]);
});

it('flags an active model with no enabled route', function () {
    $model = ModelCatalog::factory()->create(['slug' => 'empty']);
    ModelRoute::factory()->forCatalog($model)->disabled()->create();

    $report = app(ModelHealthReport::class)->run();

    expect(codes($report, 'empty'))->toBe(['no_enabled_route'])
        ->and($report['healthy'])->toBeFalse();
});

it('does not inspect a model that is switched off', function () {
    $model = ModelCatalog::factory()->create(['slug' => 'dormant', 'is_active' => false]);
    ModelRoute::factory()->forCatalog($model)->create(['execution_provider' => 'mistral']);

    expect(codes(app(ModelHealthReport::class)->run(), 'dormant'))->toBe([]);
});

it('flags a model none of whose routes the platform can run', function () {
    config(['ai.providers.some-gateway.key' => null]);
    $model = ModelCatalog::factory()->create(['slug' => 'stranded']);
    ModelRoute::factory()->forCatalog($model)->create(['execution_provider' => 'some-gateway']);

    expect(codes(app(ModelHealthReport::class)->run(), 'stranded'))->toBe(['no_usable_route']);
});

it('calls a model that only workspaces with their own key can run informational, not broken', function () {
    config(['ai.providers.mistral.key' => null]);
    $model = ModelCatalog::factory()->create(['slug' => 'byok-only']);
    ModelRoute::factory()->forCatalog($model)->create(['execution_provider' => 'mistral', 'execution_model_id' => 'mistral-small']);

    $report = app(ModelHealthReport::class)->run();

    expect(codes($report, 'byok-only'))->toBe(['byok_only'])
        ->and($report['healthy'])->toBeTrue();
});

it('warns about a skipped route when the model still has one that works', function () {
    config(['ai.providers.some-gateway.key' => null]);
    $model = healthyModel('with-backup');
    ModelRoute::factory()->forCatalog($model)->create(['execution_provider' => 'some-gateway', 'execution_model_id' => 'x', 'priority' => 1]);

    expect(codes(app(ModelHealthReport::class)->run(), 'with-backup'))->toBe(['route_provider_not_configured']);
});

it('warns about a route nobody priced, and ignores the price of a route the platform does not pay for', function () {
    healthyModel('no-price', priced: false);

    config(['ai.providers.mistral.key' => null]);
    $byok = ModelCatalog::factory()->create(['slug' => 'byok-unpriced']);
    ModelRoute::factory()->forCatalog($byok)->create(['execution_provider' => 'mistral', 'execution_model_id' => 'm']);

    $report = app(ModelHealthReport::class)->run();

    expect(codes($report, 'no-price'))->toBe(['route_unpriced'])
        ->and(codes($report, 'byok-unpriced'))->toBe(['byok_only']);
});

it('tells a price that only lives in config from one that is in the table', function () {
    config(['billing.model_prices' => ['openai:legacy-id' => ['input' => 1, 'output' => 2]]]);
    healthyModel('legacy', priced: false);

    expect(codes(app(ModelHealthReport::class)->run(), 'legacy'))->toBe(['price_not_in_table']);
});

it('tells a price never confirmed from one gone stale', function () {
    healthyModel('unverified', priced: false);
    ModelPrice::factory()->create(['provider' => 'openai', 'model_id' => 'unverified-id', 'checked_at' => null]);

    healthyModel('old-price', priced: false);
    ModelPrice::factory()->stale(ModelHealthReport::STALE_PRICE_DAYS + 1)->create(['provider' => 'openai', 'model_id' => 'old-price-id']);

    $report = app(ModelHealthReport::class)->run();

    expect(codes($report, 'unverified'))->toBe(['price_unverified'])
        ->and(codes($report, 'old-price'))->toBe(['price_stale']);
});

it('flags a route that keeps failing, but not one that failed long ago or only once', function () {
    $recent = healthyModel('flaky');
    $recent->routes()->first()->update(['failure_count' => 5, 'last_failed_at' => now()->subHour()]);

    $old = healthyModel('was-flaky');
    $old->routes()->first()->update(['failure_count' => 5, 'last_failed_at' => now()->subDays(3)]);

    $once = healthyModel('once');
    $once->routes()->first()->update(['failure_count' => 1, 'last_failed_at' => now()->subMinute()]);

    $report = app(ModelHealthReport::class)->run();

    expect(codes($report, 'flaky'))->toBe(['route_failing'])
        ->and(codes($report, 'was-flaky'))->toBe([])
        ->and(codes($report, 'once'))->toBe([]);
});

it('flags a route whose last test failed', function () {
    $model = healthyModel('tested');
    $model->routes()->first()->update(['last_check_ok' => false, 'last_check_message' => '401 Unauthorized']);

    $finding = collect(app(ModelHealthReport::class)->run()['findings'])->firstWhere('code', 'route_check_failed');

    expect($finding['severity'])->toBe('warning')
        ->and($finding['message'])->toContain('401 Unauthorized');
});

it('watches retirement dates', function () {
    healthyModel('overdue', ['retires_at' => now()->subDay()]);
    healthyModel('soon-with', ['retires_at' => now()->addDays(10), 'replacement_slug' => 'gpt-ok']);
    healthyModel('soon-without', ['retires_at' => now()->addDays(10)]);
    healthyModel('far-off', ['retires_at' => now()->addYear()]);
    healthyModel('gpt-ok');

    $report = app(ModelHealthReport::class)->run();

    expect(codes($report, 'overdue'))->toBe(['past_retirement'])
        ->and(codes($report, 'soon-with'))->toBe(['retiring_soon'])
        ->and(codes($report, 'soon-without'))->toBe(['retiring_without_replacement'])
        ->and(codes($report, 'far-off'))->toBe([]);
});

it('flags a replacement that is missing or retired itself', function () {
    healthyModel('points-nowhere', ['replacement_slug' => 'ghost', 'is_active' => false]);
    healthyModel('dead-end', ['is_active' => false]);
    healthyModel('points-at-dead', ['replacement_slug' => 'dead-end', 'is_active' => false]);

    $report = app(ModelHealthReport::class)->run();

    expect(codes($report, 'points-nowhere'))->toBe(['replacement_unavailable'])
        ->and(codes($report, 'points-at-dead'))->toBe(['replacement_unavailable'])
        ->and(codes($report, 'dead-end'))->toBe([]);
});

it('flags the internal models the platform depends on when they are gone', function () {
    ModelCatalog::query()->where('slug', 'personal-assistant')->delete();

    $finding = collect(app(ModelHealthReport::class)->run()['findings'])->firstWhere('code', 'internal_model_missing');

    expect($finding['severity'])->toBe('error')
        ->and($finding['message'])->toContain('personal-assistant');
});

it('flags an embeddings provider without a key', function () {
    config(['ai.default_for_embeddings' => 'cohere', 'ai.providers.cohere.key' => null]);

    expect(codes(app(ModelHealthReport::class)->run()))->toBe(['embeddings_provider_not_configured']);
});

it('watches the operator\'s own endpoints', function () {
    AiEndpoint::factory()->disabled()->create(['slug' => 'switched-off']);
    $model = ModelCatalog::factory()->create(['slug' => 'house']);
    ModelRoute::factory()->forCatalog($model)->create(['execution_provider' => 'switched-off', 'execution_model_id' => 'x']);

    AiEndpoint::factory()->create(['slug' => 'broken', 'last_check_ok' => false, 'last_check_message' => 'connection refused']);

    $report = app(ModelHealthReport::class)->run();

    expect(codes($report, 'switched-off'))->toBe(['endpoint_disabled_but_routed'])
        ->and(codes($report, 'broken'))->toBe(['endpoint_check_failed'])
        ->and(codes($report, 'house'))->toContain('no_usable_route');
});

it('counts the workspace keys providers have rejected', function () {
    $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);
    AiProviderCredential::factory()->create(['workspace_id' => $workspace->id, 'validation_status' => AiProviderCredentialStatus::Invalid]);

    $finding = collect(app(ModelHealthReport::class)->run()['findings'])->firstWhere('code', 'byok_rejected_keys');

    expect($finding['severity'])->toBe('info')
        ->and($finding['message'])->toContain('1 workspace key');
});

it('lists errors before warnings before info', function () {
    healthyModel('priceless', priced: false);
    $model = ModelCatalog::factory()->create(['slug' => 'empty']);
    ModelRoute::factory()->forCatalog($model)->disabled()->create();
    healthyModel('soon', ['retires_at' => now()->addDays(5), 'replacement_slug' => 'priceless']);

    $severities = array_column(app(ModelHealthReport::class)->run()['findings'], 'severity');

    expect($severities)->toBe(['error', 'warning', 'info']);
});
