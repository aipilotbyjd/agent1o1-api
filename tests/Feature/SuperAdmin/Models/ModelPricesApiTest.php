<?php

use App\Enums\Ai\ModelPriceSource;
use App\Enums\SuperAdmin\SuperAdminRole;
use App\Models\Ai\ModelPrice;
use App\Models\SuperAdmin\SuperAdminAuditLog;
use App\Services\Ai\ModelPrices;

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
        ->and(app(ModelPrices::class)->for('openai', 'gpt-x'))->toBe(['input' => 10.0, 'output' => 20.0]);

    $this->withHeaders($this->headers)->putJson(saUrl('models/prices'), pricePayload(['input_per_mtok' => 99, 'output_per_mtok' => 99]))->assertOk();

    expect(app(ModelPrices::class)->for('openai', 'gpt-x'))->toBe(['input' => 99.0, 'output' => 99.0]);
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
