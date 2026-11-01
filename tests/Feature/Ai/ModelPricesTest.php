<?php

use App\Enums\Ai\ModelPriceSource;
use App\Models\Ai\ModelPrice;
use App\Services\Ai\ModelPrices;
use App\Services\Billing\CreditMeter;

/**
 * @return array<string, mixed>
 */
function usageOf(string $provider, string $model, int $prompt = 1_000_000, int $completion = 500_000): array
{
    return ['provider' => $provider, 'model' => $model, 'prompt_tokens' => $prompt, 'completion_tokens' => $completion];
}

it('prices a call from the table', function () {
    ModelPrice::factory()->create(['provider' => 'openai', 'model_id' => 'gpt-x', 'input_per_mtok' => 10, 'output_per_mtok' => 20]);

    expect(app(CreditMeter::class)->dollarCost(usageOf('openai', 'gpt-x')))->toBe(20.0);
});

it('lets the table override the older config price, and falls back to config without a row', function () {
    config(['billing.model_prices' => [
        'openai:gpt-x' => ['input' => 1.0, 'output' => 2.0],
        'openai:gpt-y' => ['input' => 3.0, 'output' => 4.0],
    ]]);
    ModelPrice::factory()->create(['provider' => 'openai', 'model_id' => 'gpt-x', 'input_per_mtok' => 10, 'output_per_mtok' => 20]);

    $prices = app(ModelPrices::class);

    expect($prices->for('openai', 'gpt-x'))->toBe(['input' => 10.0, 'output' => 20.0])
        ->and($prices->for('openai', 'gpt-y'))->toBe(['input' => 3.0, 'output' => 4.0])
        ->and($prices->isStored('openai', 'gpt-x'))->toBeTrue()
        ->and($prices->isStored('openai', 'gpt-y'))->toBeFalse();
});

it('reads config prices for model ids that contain dots', function () {
    config(['billing.model_prices' => ['openrouter:anthropic/claude-haiku-4.5' => ['input' => 1, 'output' => 5]]]);

    expect(app(ModelPrices::class)->for('openrouter', 'anthropic/claude-haiku-4.5'))->toBe(['input' => 1.0, 'output' => 5.0]);
});

it('has no price for a model nobody priced, and bills it by the flat ratio', function () {
    config(['billing.model_prices' => []]);

    $meter = app(CreditMeter::class);

    expect(app(ModelPrices::class)->for('openai', 'mystery'))->toBeNull()
        ->and(app(ModelPrices::class)->for(null, 'mystery'))->toBeNull()
        ->and($meter->dollarCost(usageOf('openai', 'mystery')))->toBeNull()
        ->and($meter->dollarCost(['prompt_tokens' => 5]))->toBeNull();
});

it('turns a priced call into credits at the credit value', function () {
    config(['billing.credit_value_usd' => 0.005, 'billing.model_prices' => []]);
    ModelPrice::factory()->create(['provider' => 'openai', 'model_id' => 'gpt-x', 'input_per_mtok' => 5, 'output_per_mtok' => 0]);

    $priced = app(CreditMeter::class)->estimateChatMessage('openai', 'gpt-x');
    $unpriced = app(CreditMeter::class)->estimateChatMessage('openai', 'gpt-unknown');

    // 2,000 prompt tokens at $5 per million is a cent: two credits at half a cent each.
    // The flat ratio would charge ceil(2,500 / 1,000) = 3 for the tokens.
    expect($priced)->toBeLessThan($unpriced);
});

it('charges nothing for tokens served on the workspace\'s own key', function () {
    ModelPrice::factory()->create(['provider' => 'openai', 'model_id' => 'gpt-x', 'input_per_mtok' => 500, 'output_per_mtok' => 500]);

    $own = app(CreditMeter::class)->estimateChatMessage('byok-123', 'gpt-x');
    $platform = app(CreditMeter::class)->estimateChatMessage('openai', 'gpt-x');

    expect($own)->toBeLessThan($platform);
});

it('sees a price change on the next lookup', function () {
    $price = ModelPrice::factory()->create(['provider' => 'openai', 'model_id' => 'gpt-x', 'input_per_mtok' => 1, 'output_per_mtok' => 1]);

    expect(app(ModelPrices::class)->for('openai', 'gpt-x')['input'])->toBe(1.0);

    $price->update(['input_per_mtok' => 9]);

    expect(app(ModelPrices::class)->for('openai', 'gpt-x')['input'])->toBe(9.0);

    $price->delete();

    expect(app(ModelPrices::class)->for('openai', 'gpt-x'))->toBeNull();
});

it('imports the older config prices once and never overwrites a stored one', function () {
    config(['billing.model_prices' => [
        'openai:gpt-a' => ['input' => 1.0, 'output' => 2.0],
        'openai:gpt-b' => ['input' => 3.0, 'output' => 4.0],
    ]]);
    ModelPrice::factory()->create(['provider' => 'openai', 'model_id' => 'gpt-b', 'input_per_mtok' => 99, 'output_per_mtok' => 99]);

    expect(app(ModelPrices::class)->importFromConfig())->toBe(1)
        ->and(app(ModelPrices::class)->importFromConfig())->toBe(0);

    $imported = ModelPrice::query()->where('model_id', 'gpt-a')->sole();

    expect($imported->source)->toBe(ModelPriceSource::Config)
        ->and($imported->checked_at)->toBeNull()
        ->and(ModelPrice::query()->where('model_id', 'gpt-b')->sole()->input_per_mtok)->toBe(99.0);
});

it('seeds the table from config through the seeder', function () {
    config(['billing.model_prices' => ['openai:gpt-seeded' => ['input' => 1.0, 'output' => 2.0]]]);

    $this->syncSetup('model-prices');

    expect(ModelPrice::query()->where('model_id', 'gpt-seeded')->exists())->toBeTrue();
});
