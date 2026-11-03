<?php

use App\Enums\Ai\ModelPriceSource;
use App\Enums\Ai\ModelPriceStatus;
use App\Enums\SuperAdmin\SuperAdminRole;
use App\Models\Ai\AiCallEvent;
use App\Models\Ai\ModelCatalog;
use App\Models\Ai\ModelPrice;
use App\Models\Ai\ModelRoute;
use App\Models\SuperAdmin\SuperAdminAuditLog;
use App\Services\Ai\ModelPrices;
use Illuminate\Support\Facades\Http;

const PRICE_REASON = 'Provider published new prices';

beforeEach(function () {
    config(['billing.model_prices' => []]);

    $this->finance = superAdmin(SuperAdminRole::Finance);
    $this->headers = superAdminHeaders($this->finance);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function pricePayload(array $overrides = []): array
{
    return [
        'reason' => PRICE_REASON,
        'provider' => 'openai',
        'model_id' => 'gpt-x',
        'input_per_mtok' => 2.5,
        'output_per_mtok' => 10,
        'source' => 'provider_list',
        ...$overrides,
    ];
}

it('creates a price, then replaces it, recording before and after', function () {
    $this->withHeaders($this->headers)->putJson(saUrl('models/prices'), pricePayload())
        ->assertOk()
        ->assertJsonPath('data.input_per_mtok', 2.5)
        ->assertJsonPath('data.source', 'provider_list');

    $this->withHeaders($this->headers)->putJson(saUrl('models/prices'), pricePayload(['input_per_mtok' => 3, 'source' => 'contract', 'notes' => 'Committed spend']))->assertOk();

    expect(ModelPrice::query()->count())->toBe(1);

    $log = SuperAdminAuditLog::query()->where('action', 'models.price.set')->latest('created_at')->orderByDesc('id')->get();

    expect($log)->toHaveCount(2)
        ->and($log->contains(fn ($entry) => $entry->before === null))->toBeTrue()
        ->and($log->contains(fn ($entry) => (float) ($entry->before['input_per_mtok'] ?? 0) === 2.5 && (float) $entry->after['input_per_mtok'] === 3.0))->toBeTrue();
});

it('confirms a price by setting it, and a call is billed at the new price straight away', function () {
    $this->withHeaders($this->headers)->putJson(saUrl('models/prices'), pricePayload(['input_per_mtok' => 10, 'output_per_mtok' => 20]))->assertOk();

    expect(ModelPrice::query()->sole()->checked_at)->not->toBeNull()
        ->and(app(ModelPrices::class)->for('openai', 'gpt-x'))->toBe(['input' => 10.0, 'output' => 20.0, 'cache_read' => null, 'cache_write' => null]);

    $this->withHeaders($this->headers)->putJson(saUrl('models/prices'), pricePayload(['input_per_mtok' => 99, 'output_per_mtok' => 99]))->assertOk();

    expect(app(ModelPrices::class)->for('openai', 'gpt-x'))->toBe(['input' => 99.0, 'output' => 99.0, 'cache_read' => null, 'cache_write' => null]);
});

it('validates a price', function (array $input, string $error) {
    $this->withHeaders($this->headers)->putJson(saUrl('models/prices'), pricePayload($input))
        ->assertUnprocessable()->assertJsonValidationErrors($error);
})->with([
    'no reason' => [['reason' => ''], 'reason'],
    'a negative price' => [['input_per_mtok' => -1], 'input_per_mtok'],
    'an absurd price' => [['output_per_mtok' => 1_000_000], 'output_per_mtok'],
    'no output price' => [['output_per_mtok' => null], 'output_per_mtok'],
    'a source that is not allowed' => [['source' => 'config'], 'source'],
    'an unknown source' => [['source' => 'a-hunch'], 'source'],
    'a workspace key' => [['provider' => 'byok-0f3a'], 'provider'],
]);

it('lists prices, filters them, and finds the stale ones', function () {
    ModelPrice::factory()->create(['provider' => 'openai', 'model_id' => 'gpt-fresh']);
    ModelPrice::factory()->stale(200)->create(['provider' => 'openai', 'model_id' => 'gpt-stale']);
    ModelPrice::factory()->create(['provider' => 'anthropic', 'model_id' => 'claude-x', 'checked_at' => null]);

    $ids = fn (string $query) => array_column($this->withHeaders($this->headers)->getJson(saUrl("models/prices?{$query}"))->json('data'), 'model_id');

    expect($ids(''))->toBe(['claude-x', 'gpt-fresh', 'gpt-stale'])
        ->and($ids('provider=anthropic'))->toBe(['claude-x'])
        ->and($ids('q=stale'))->toBe(['gpt-stale'])
        ->and($ids('stale=1'))->toBe(['claude-x', 'gpt-stale']);
});

it('lets every role read the prices', function (SuperAdminRole $role) {
    $this->withHeaders(superAdminHeaders(superAdmin($role)))->getJson(saUrl('models/prices'))->assertOk();
})->with(array_map(fn (SuperAdminRole $role) => [$role], SuperAdminRole::cases()));

it('removes a price, so the model goes back to being unpriced', function () {
    $price = ModelPrice::factory()->create(['provider' => 'openai', 'model_id' => 'gpt-x']);

    $this->withHeaders($this->headers)->deleteJson(saUrl("models/prices/{$price->id}"), ['reason' => 'Added by mistake'])->assertNoContent();

    expect(ModelPrice::query()->count())->toBe(0)
        ->and(app(ModelPrices::class)->for('openai', 'gpt-x'))->toBeNull()
        ->and(SuperAdminAuditLog::query()->where('action', 'models.price.removed')->firstOrFail()->before)->toMatchArray(['model_id' => 'gpt-x']);
});

it('needs a reason to remove a price', function () {
    $price = ModelPrice::factory()->create();

    $this->withHeaders($this->headers)->deleteJson(saUrl("models/prices/{$price->id}"))->assertUnprocessable()->assertJsonValidationErrors('reason');
});

it('keeps price changes to finance and owners', function (SuperAdminRole $role, int $status) {
    $this->withHeaders(superAdminHeaders(superAdmin($role)))->putJson(saUrl('models/prices'), pricePayload())->assertStatus($status);
})->with([
    'owner' => [SuperAdminRole::Owner, 200],
    'finance' => [SuperAdminRole::Finance, 200],
    'operator' => [SuperAdminRole::Operator, 403],
    'support' => [SuperAdminRole::Support, 403],
    'viewer' => [SuperAdminRole::Viewer, 403],
]);

it('accepts every source a person may choose', function (ModelPriceSource $source) {
    $this->withHeaders($this->headers)->putJson(saUrl('models/prices'), pricePayload(['source' => $source->value]))->assertOk();
})->with([[ModelPriceSource::ProviderList], [ModelPriceSource::Contract], [ModelPriceSource::Estimate]]);

it('saves a model\'s cache prices and bills cached tokens at them', function () {
    $this->withHeaders($this->headers)
        ->putJson(saUrl('models/prices'), pricePayload(['input_per_mtok' => 3, 'output_per_mtok' => 15, 'cache_read_per_mtok' => 0.3, 'cache_write_per_mtok' => 3.75]))
        ->assertOk()
        ->assertJsonPath('data.cache_read_per_mtok', 0.3)
        ->assertJsonPath('data.cache_write_per_mtok', 3.75);

    expect(app(ModelPrices::class)->for('openai', 'gpt-x'))->toBe(['input' => 3.0, 'output' => 15.0, 'cache_read' => 0.3, 'cache_write' => 3.75]);
});

it('rejects a negative cache price with 422', function () {
    $this->withHeaders($this->headers)
        ->putJson(saUrl('models/prices'), pricePayload(['cache_read_per_mtok' => -1]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('cache_read_per_mtok');

    expect(ModelPrice::query()->count())->toBe(0);
});

it('saves a price as verified by whoever set it, unless told otherwise', function () {
    $this->withHeaders($this->headers)->putJson(saUrl('models/prices'), pricePayload())
        ->assertOk()
        ->assertJsonPath('data.status', 'verified')
        ->assertJsonPath('data.verified_by', $this->finance->getKey());

    $this->withHeaders($this->headers)->putJson(saUrl('models/prices'), pricePayload(['status' => 'unverified']))
        ->assertOk()
        ->assertJsonPath('data.status', 'unverified')
        ->assertJsonPath('data.verified_at', null);
});

it('saves long-context rates, which need both prices once a threshold is set', function () {
    $this->withHeaders($this->headers)->putJson(saUrl('models/prices'), pricePayload(['long_context_threshold' => 200_000]))
        ->assertUnprocessable()->assertJsonValidationErrors(['long_input_per_mtok', 'long_output_per_mtok']);

    $this->withHeaders($this->headers)->putJson(saUrl('models/prices'), pricePayload(['long_context_threshold' => 200_000, 'long_input_per_mtok' => 5, 'long_output_per_mtok' => 20]))
        ->assertOk()
        ->assertJsonPath('data.long_context_threshold', 200_000);

    expect(app(ModelPrices::class)->for('openai', 'gpt-x'))->toMatchArray(['long_context_threshold' => 200_000, 'long_input' => 5.0, 'long_output' => 20.0]);
});

it('verifies an existing price without changing its rates, and audits it', function () {
    $price = ModelPrice::factory()->unverified()->create(['provider' => 'openai', 'model_id' => 'gpt-x', 'input_per_mtok' => 4]);

    $this->withHeaders($this->headers)->postJson(saUrl("models/prices/{$price->id}/verify"), ['reason' => PRICE_REASON])
        ->assertOk()
        ->assertJsonPath('data.status', 'verified')
        ->assertJsonPath('data.input_per_mtok', 4);

    expect(SuperAdminAuditLog::query()->where('action', 'models.price.verified')->exists())->toBeTrue();
});

it('lists every version a price has had, newest first', function () {
    $price = ModelPrice::factory()->create(['provider' => 'openai', 'model_id' => 'gpt-x', 'input_per_mtok' => 1]);
    $price->update(['input_per_mtok' => 2]);

    $this->withHeaders($this->headers)->getJson(saUrl("models/prices/{$price->id}/versions"))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.version', 2)
        ->assertJsonPath('data.0.input_per_mtok', 2)
        ->assertJsonPath('data.1.effective_to', fn ($value) => $value !== null);
});

it('reports which enabled routes the platform pays for still lack a verified price', function () {
    config(['ai.providers.openai.key' => 'platform-key']);
    $verified = ModelCatalog::factory()->create(['slug' => 'trusted']);
    ModelRoute::factory()->forCatalog($verified)->create(['execution_provider' => 'openai', 'execution_model_id' => 'trusted-id']);
    ModelPrice::factory()->create(['provider' => 'openai', 'model_id' => 'trusted-id']);
    $guessed = ModelCatalog::factory()->create(['slug' => 'guessed']);
    ModelRoute::factory()->forCatalog($guessed)->create(['execution_provider' => 'openai', 'execution_model_id' => 'guessed-id']);
    AiCallEvent::factory()->count(3)->create(['provider' => 'openai', 'model' => 'guessed-id', 'provider_cost_usd' => 0.5]);

    $this->withHeaders($this->headers)->getJson(saUrl('models/prices/readiness'))
        ->assertOk()
        ->assertJsonPath('data.summary.verified', 1)
        ->assertJsonPath('data.summary.unpriced', 1)
        ->assertJsonPath('data.summary.would_block', 1)
        ->assertJsonPath('data.summary.ready_to_enforce', false)
        ->assertJsonPath('data.summary.untrusted_calls', 3)
        ->assertJsonPath('data.summary.untrusted_cost_usd', 1.5);

    $this->artisan('model-prices:check')->assertFailed();
});

it('refuses to switch on a platform route without a verified price while prices are enforced', function () {
    config(['ai.providers.openai.key' => 'platform-key', 'billing.price_enforcement' => 'enforce']);
    $model = ModelCatalog::factory()->create(['slug' => 'new-model']);
    ModelRoute::factory()->forCatalog($model)->create(['execution_provider' => 'openai', 'execution_model_id' => 'live']);
    ModelPrice::factory()->create(['provider' => 'openai', 'model_id' => 'live']);
    $operator = superAdminHeaders(superAdmin(SuperAdminRole::Operator));

    $this->withHeaders($operator)->postJson(saUrl("models/{$model->id}/routes"), ['reason' => PRICE_REASON, 'execution_provider' => 'openai', 'execution_model_id' => 'unpriced', 'is_enabled' => true])
        ->assertStatus(409);

    $this->withHeaders($operator)->postJson(saUrl("models/{$model->id}/routes"), ['reason' => PRICE_REASON, 'execution_provider' => 'openai', 'execution_model_id' => 'unpriced', 'is_enabled' => false])
        ->assertSuccessful();
});

it('fills unverified prices from the providers\' list and reports drift on verified ones', function () {
    Http::fake(['openrouter.ai/*' => Http::response(['data' => [
        ['id' => 'anthropic/claude-new', 'pricing' => ['prompt' => '0.000003', 'completion' => '0.000015', 'input_cache_read' => '0.0000003']],
        ['id' => 'openai/gpt-old', 'pricing' => ['prompt' => '0.000005', 'completion' => '0.00002']],
    ]])]);
    $new = ModelCatalog::factory()->create(['slug' => 'claude-new']);
    ModelRoute::factory()->forCatalog($new)->create(['execution_provider' => 'anthropic', 'execution_model_id' => 'claude-new']);
    ModelRoute::factory()->forCatalog($new)->create(['execution_provider' => 'openrouter', 'execution_model_id' => 'anthropic/claude-new', 'is_enabled' => false]);
    $old = ModelCatalog::factory()->create(['slug' => 'gpt-old']);
    ModelRoute::factory()->forCatalog($old)->create(['execution_provider' => 'openai', 'execution_model_id' => 'gpt-old']);
    ModelRoute::factory()->forCatalog($old)->create(['execution_provider' => 'openrouter', 'execution_model_id' => 'openai/gpt-old', 'is_enabled' => false]);
    ModelPrice::factory()->create(['provider' => 'openai', 'model_id' => 'gpt-old', 'input_per_mtok' => 2.5, 'output_per_mtok' => 10]);

    $this->artisan('model-prices:sync')->assertSuccessful();

    $created = ModelPrice::query()->where('provider', 'anthropic')->sole();

    expect($created->status)->toBe(ModelPriceStatus::Unverified)
        ->and($created->input_per_mtok)->toBe(3.0)
        ->and($created->output_per_mtok)->toBe(15.0)
        ->and($created->cache_read_per_mtok)->toBe(0.3)
        ->and(ModelPrice::query()->where('provider', 'openai')->sole()->input_per_mtok)->toBe(2.5);
});
