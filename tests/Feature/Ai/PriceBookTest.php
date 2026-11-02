<?php

use App\Ai\Usage\TokenUsage;
use App\Ai\Usage\UsagePricer;
use App\Enums\Ai\ModelPriceStatus;
use App\Exceptions\ModelPriceUnverifiedException;
use App\Models\Ai\AiProviderCredential;
use App\Models\Ai\ModelCatalog;
use App\Models\Ai\ModelPrice;
use App\Models\Ai\ModelPriceVersion;
use App\Models\Ai\ModelRoute;
use App\Models\User;
use App\Services\Ai\ByokProviderRegistrar;
use App\Services\Ai\ModelPrices;
use App\Services\Ai\PriceGate;
use App\Services\Billing\CreditMeter;
use App\Services\Workspaces\WorkspaceService;

beforeEach(function () {
    config([
        'billing.model_prices' => [],
        'billing.credit_value_usd' => 0.005,
        'ai.providers.openai.key' => 'platform-key',
    ]);
});

it('opens a new price version whenever the rates or status change, and only then', function () {
    $price = ModelPrice::factory()->create(['provider' => 'openai', 'model_id' => 'gpt-x', 'input_per_mtok' => 2, 'output_per_mtok' => 8]);

    $price->update(['notes' => 'just a note']);
    expect($price->versions()->count())->toBe(1);

    $price->update(['input_per_mtok' => 3]);
    $versions = $price->versions()->orderBy('version')->get();

    expect($versions)->toHaveCount(2)
        ->and($versions[0]->effective_to)->not->toBeNull()
        ->and($versions[1]->effective_to)->toBeNull()
        ->and($versions[1]->version)->toBe(2)
        ->and($versions[1]->input_per_mtok)->toBe(3.0)
        ->and($price->currentVersion()->id)->toBe($versions[1]->id);
});

it('keeps the version history after a price is deleted, closed', function () {
    $price = ModelPrice::factory()->create();
    $price->delete();

    expect(ModelPriceVersion::query()->count())->toBe(1)
        ->and(ModelPriceVersion::query()->sole()->effective_to)->not->toBeNull();
});

it('reports a price\'s status and the version in force', function () {
    $price = ModelPrice::factory()->unverified()->create(['provider' => 'openai', 'model_id' => 'gpt-x']);
    $meta = app(ModelPrices::class)->meta('openai', 'gpt-x');

    expect($meta['status'])->toBe(ModelPriceStatus::Unverified)
        ->and($meta['version_id'])->toBe($price->currentVersion()->id)
        ->and(app(ModelPrices::class)->status('openai', 'nothing'))->toBeNull();
});

it('treats config prices as demo prices', function () {
    config(['billing.model_prices' => ['openai:legacy' => ['input' => 1, 'output' => 2]]]);

    expect(app(ModelPrices::class)->status('openai', 'legacy'))->toBe(ModelPriceStatus::Demo);
});

it('prices an unpriced model at the most expensive priced model of its cost tier', function () {
    $tier3 = ModelCatalog::factory()->create(['slug' => 'frontier', 'cost_tier' => 3]);
    ModelRoute::factory()->forCatalog($tier3)->create(['execution_provider' => 'openai', 'execution_model_id' => 'priced-a']);
    ModelRoute::factory()->forCatalog($tier3)->create(['execution_provider' => 'anthropic', 'execution_model_id' => 'priced-b']);
    ModelRoute::factory()->forCatalog($tier3)->create(['execution_provider' => 'xai', 'execution_model_id' => 'unpriced']);
    ModelPrice::factory()->create(['provider' => 'openai', 'model_id' => 'priced-a', 'input_per_mtok' => 5, 'output_per_mtok' => 20]);
    ModelPrice::factory()->create(['provider' => 'anthropic', 'model_id' => 'priced-b', 'input_per_mtok' => 3, 'output_per_mtok' => 40]);

    expect(app(ModelPrices::class)->fallbackFor('xai', 'unpriced'))->toMatchArray(['input' => 5.0, 'output' => 40.0]);
});

it('falls back to the configured high price when nothing in the tier is priced', function () {
    config(['billing.unpriced_fallback' => ['mode' => 'tier_max', 'input' => 15, 'output' => 75]]);

    expect(app(ModelPrices::class)->fallbackFor('mystery', 'model'))->toMatchArray(['input' => 15.0, 'output' => 75.0]);
});

it('bills an unpriced call at the fallback price instead of the cheap flat ratio', function () {
    config(['billing.unpriced_fallback' => ['mode' => 'tier_max', 'input' => 15, 'output' => 75]]);
    $usage = ['input_tokens' => 100_000, 'output_tokens' => 10_000, 'provider' => 'mystery', 'model' => 'model'];

    // $1.50 + $0.75 = $2.25 = 450 credits. The flat ratio would have been 110.
    expect(app(CreditMeter::class)->costForEmbeddings($usage))->toBe(450);

    config(['billing.unpriced_fallback.mode' => 'flat']);

    expect(app(CreditMeter::class)->costForEmbeddings($usage))->toBe(110);
});

it('reports the provider cost of a call with its price status, counting unpriced calls at the fallback', function () {
    config(['billing.unpriced_fallback' => ['mode' => 'tier_max', 'input' => 10, 'output' => 10]]);
    $price = ModelPrice::factory()->create(['provider' => 'openai', 'model_id' => 'gpt-x', 'input_per_mtok' => 2, 'output_per_mtok' => 8]);
    $meter = app(CreditMeter::class);

    expect($meter->providerCost(['input_tokens' => 1_000_000, 'output_tokens' => 0, 'provider' => 'openai', 'model' => 'gpt-x']))
        ->toBe(['usd' => 2.0, 'priced' => true, 'status' => 'verified', 'version_id' => $price->currentVersion()->id])
        ->and($meter->providerCost(['input_tokens' => 1_000_000, 'output_tokens' => 0, 'provider' => 'openai', 'model' => 'nope']))
        ->toMatchArray(['usd' => 10.0, 'priced' => false, 'status' => 'unpriced'])
        ->and($meter->providerCost(['input_tokens' => 1_000_000, 'provider' => 'byok-abc', 'model' => 'gpt-x'])['usd'])->toBe(0.0);
});

it('bills the whole request at the long-context rates once its input passes the threshold', function () {
    $price = ['input' => 2.0, 'output' => 10.0, 'cache_read' => 0.2, 'cache_write' => null, 'long_context_threshold' => 200_000, 'long_input' => 4.0, 'long_output' => 15.0];
    $pricer = app(UsagePricer::class);

    expect($pricer->dollars(new TokenUsage(inputTokens: 100_000, outputTokens: 10_000), $price))->toBe(0.3)
        ->and($pricer->dollars(new TokenUsage(inputTokens: 300_000, outputTokens: 10_000), $price))->toBe(1.35)
        ->and($pricer->dollars(new TokenUsage(inputTokens: 300_000, outputTokens: 0, cacheReadInputTokens: 100_000), $price))->toBe(0.84);
});

describe('enforcement', function () {
    beforeEach(function () {
        $this->workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);
    });

    it('lets every call through while warning, and refuses untrusted platform calls while enforcing', function () {
        ModelPrice::factory()->create(['provider' => 'openai', 'model_id' => 'trusted']);
        ModelPrice::factory()->unverified()->create(['provider' => 'openai', 'model_id' => 'guessed']);
        $gate = app(PriceGate::class);

        config(['billing.price_enforcement' => 'warn']);
        expect($gate->allowsPlatformCall('openai', 'guessed'))->toBeTrue()
            ->and($gate->allowsPlatformCall('openai', 'unpriced'))->toBeTrue();

        config(['billing.price_enforcement' => 'enforce']);
        expect($gate->allowsPlatformCall('openai', 'trusted'))->toBeTrue()
            ->and($gate->allowsPlatformCall('openai', 'guessed'))->toBeFalse()
            ->and($gate->allowsPlatformCall('openai', 'unpriced'))->toBeFalse()
            ->and($gate->allowsPlatformCall('openai', null))->toBeTrue();
    });

    it('refuses a call whose only platform route has no verified price', function () {
        config(['billing.price_enforcement' => 'enforce']);

        expect(fn () => app(ByokProviderRegistrar::class)->apply(['openai' => 'unpriced'], null, $this->workspace->id, null))
            ->toThrow(ModelPriceUnverifiedException::class);
    });

    it('skips an untrusted platform route and fails over to a verified one', function () {
        config(['billing.price_enforcement' => 'enforce', 'ai.providers.anthropic.key' => 'platform-key']);
        ModelPrice::factory()->create(['provider' => 'anthropic', 'model_id' => 'claude-x']);

        [$chain] = app(ByokProviderRegistrar::class)->apply(['openai' => 'unpriced', 'anthropic' => 'claude-x'], null, $this->workspace->id, null);

        expect($chain)->toBe(['anthropic' => 'claude-x']);
    });

    it('still runs an unpriced model on the workspace\'s own key', function () {
        config(['billing.price_enforcement' => 'enforce']);
        AiProviderCredential::factory()->create(['workspace_id' => $this->workspace->id, 'execution_provider' => 'openai']);

        [$chain] = app(ByokProviderRegistrar::class)->apply(['openai' => 'unpriced'], null, $this->workspace->id, null);

        expect(array_keys($chain))->toHaveCount(1)
            ->and(ByokProviderRegistrar::isByok(array_key_first($chain)))->toBeTrue();
    });
});
