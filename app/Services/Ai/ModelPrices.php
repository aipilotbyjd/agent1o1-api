<?php

namespace App\Services\Ai;

use App\Enums\Ai\ModelPriceSource;
use App\Enums\Ai\ModelPriceStatus;
use App\Models\Ai\ModelPrice;
use App\Models\Ai\ModelPriceVersion;
use App\Models\Ai\ModelRoute;
use Illuminate\Support\Facades\Cache;

/**
 * What a backend model costs per million tokens: the `model_prices` table
 * first, then the older `config/billing.php` `model_prices` map, so prices
 * that were never moved into the table keep working. Every price carries its
 * status (demo, unverified, verified) and, for stored prices, the version it
 * was billed at.
 *
 * A model in neither is unpriced. `fallbackFor()` then gives a deliberately
 * high price, the most expensive priced model of the same cost tier (or
 * `billing.unpriced_fallback`), so a missing price overcharges rather than
 * silently undercharging.
 *
 * The table is cached for a few minutes as one map; saving or deleting a
 * `ModelPrice` drops the cache.
 *
 * @phpstan-type Price array{input: float, output: float, cache_read: float|null, cache_write: float|null, long_context_threshold?: int|null, long_input?: float|null, long_output?: float|null, status?: string, version_id?: string|null}
 */
class ModelPrices
{
    public const string CACHE_KEY = 'model-prices:all';

    private const string TIERS_CACHE_KEY = 'model-prices:tiers';

    private const int CACHE_TTL_SECONDS = 300;

    /**
     * The model's billing rates in US dollars per million tokens, or null
     * when it has no price.
     *
     * @return array{input: float, output: float, cache_read: float|null, cache_write: float|null, long_context_threshold?: int, long_input?: float|null, long_output?: float|null}|null
     */
    public function for(?string $provider, ?string $model): ?array
    {
        $entry = $this->entry($provider, $model);

        return $entry === null ? null : $this->rates($entry);
    }

    /**
     * How far the price can be trusted and which version is in effect, or
     * null when the model has no price.
     *
     * @return array{status: ModelPriceStatus, version_id: string|null}|null
     */
    public function meta(?string $provider, ?string $model): ?array
    {
        $entry = $this->entry($provider, $model);

        if ($entry === null) {
            return null;
        }

        return [
            'status' => ModelPriceStatus::tryFrom($entry['status'] ?? '') ?? ModelPriceStatus::Demo,
            'version_id' => $entry['version_id'] ?? null,
        ];
    }

    /**
     * How far the price of `$provider`/`$model` can be trusted, or null when
     * it has none.
     */
    public function status(?string $provider, ?string $model): ?ModelPriceStatus
    {
        return $this->meta($provider, $model)['status'] ?? null;
    }

    /**
     * The price to bill an unpriced call at: the highest input and output
     * rates among priced models of the same cost tier, or
     * `billing.unpriced_fallback` when the tier has none (or the route is not
     * in the catalog).
     *
     * @return array{input: float, output: float, cache_read: null, cache_write: null}
     */
    public function fallbackFor(?string $provider, ?string $model): array
    {
        $tiers = $this->tiers();
        $tier = $tiers["{$provider}:{$model}"] ?? null;
        $input = 0.0;
        $output = 0.0;

        if ($tier !== null) {
            foreach ([...$this->legacy(), ...$this->stored()] as $key => $price) {
                if (($tiers[$key] ?? null) === $tier) {
                    $input = max($input, $price['input']);
                    $output = max($output, $price['output']);
                }
            }
        }

        if ($input <= 0 && $output <= 0) {
            $input = (float) config('billing.unpriced_fallback.input', 15.0);
            $output = (float) config('billing.unpriced_fallback.output', 75.0);
        }

        return ['input' => $input, 'output' => $output, 'cache_read' => null, 'cache_write' => null];
    }

    /**
     * @return Price|null
     */
    private function entry(?string $provider, ?string $model): ?array
    {
        if ($provider === null || $model === null) {
            return null;
        }

        $key = "{$provider}:{$model}";

        return $this->stored()[$key] ?? $this->legacy()[$key] ?? null;
    }

    /**
     * The billing rates of an entry: the four token rates, plus the
     * long-context rates only for a model that has them, so callers that
     * compare rates see the same shape they always have.
     *
     * @param  Price  $entry
     * @return array{input: float, output: float, cache_read: float|null, cache_write: float|null, long_context_threshold?: int, long_input?: float|null, long_output?: float|null}
     */
    private function rates(array $entry): array
    {
        $rates = [
            'input' => $entry['input'],
            'output' => $entry['output'],
            'cache_read' => $entry['cache_read'],
            'cache_write' => $entry['cache_write'],
        ];

        if (($entry['long_context_threshold'] ?? null) !== null) {
            $rates['long_context_threshold'] = $entry['long_context_threshold'];
            $rates['long_input'] = $entry['long_input'] ?? null;
            $rates['long_output'] = $entry['long_output'] ?? null;
        }

        return $rates;
    }

    public function isStored(string $provider, string $model): bool
    {
        return isset($this->stored()["{$provider}:{$model}"]);
    }

    /**
     * Copies the config map into the table. These were never confirmed
     * against the providers, so they arrive as demo prices.
     */
    public function importFromConfig(): int
    {
        $created = 0;

        foreach ($this->legacy() as $key => $price) {
            [$provider, $model] = explode(':', $key, 2);

            $row = ModelPrice::query()->firstOrCreate(
                ['provider' => $provider, 'model_id' => $model],
                [
                    'input_per_mtok' => $price['input'],
                    'output_per_mtok' => $price['output'],
                    'cache_read_per_mtok' => $price['cache_read'],
                    'cache_write_per_mtok' => $price['cache_write'],
                    'source' => ModelPriceSource::Config,
                    'status' => ModelPriceStatus::Demo,
                    'checked_at' => null,
                    'notes' => 'Imported from config/billing.php',
                ],
            );

            $created += $row->wasRecentlyCreated ? 1 : 0;
        }

        return $created;
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::TIERS_CACHE_KEY);
    }

    /**
     * @return array<string, Price>
     */
    private function stored(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function (): array {
            $versions = ModelPriceVersion::query()
                ->whereNull('effective_to')
                ->pluck('id', 'model_price_id');

            return ModelPrice::query()
                ->get()
                ->mapWithKeys(fn (ModelPrice $price): array => [
                    "{$price->provider}:{$price->model_id}" => [
                        'input' => $price->input_per_mtok,
                        'output' => $price->output_per_mtok,
                        'cache_read' => $price->cache_read_per_mtok,
                        'cache_write' => $price->cache_write_per_mtok,
                        'long_context_threshold' => $price->long_context_threshold,
                        'long_input' => $price->long_input_per_mtok,
                        'long_output' => $price->long_output_per_mtok,
                        'status' => ($price->status ?? ModelPriceStatus::Unverified)->value,
                        'version_id' => $versions[$price->id] ?? null,
                    ],
                ])
                ->all();
        });
    }

    /**
     * Each catalog route's cost tier, keyed like the price maps.
     *
     * @return array<string, int>
     */
    private function tiers(): array
    {
        return Cache::remember(self::TIERS_CACHE_KEY, self::CACHE_TTL_SECONDS, fn (): array => ModelRoute::query()
            ->join('model_catalog', 'model_catalog.id', '=', 'model_routes.model_catalog_id')
            ->whereNotNull('model_catalog.cost_tier')
            ->get(['model_routes.execution_provider', 'model_routes.execution_model_id', 'model_catalog.cost_tier'])
            ->mapWithKeys(fn ($row): array => ["{$row->execution_provider}:{$row->execution_model_id}" => (int) $row->cost_tier])
            ->all());
    }

    /**
     * Read through `config()` as a whole array, never by dotted path: model
     * ids contain dots (`claude-haiku-4.5`), which a path lookup would split.
     * Config prices were never confirmed, so they are demo prices.
     *
     * @return array<string, Price>
     */
    private function legacy(): array
    {
        return collect((array) config('billing.model_prices', []))
            ->map(fn (array $price): array => [
                'input' => (float) $price['input'],
                'output' => (float) $price['output'],
                'cache_read' => isset($price['cache_read']) ? (float) $price['cache_read'] : null,
                'cache_write' => isset($price['cache_write']) ? (float) $price['cache_write'] : null,
                'status' => (string) ($price['status'] ?? ModelPriceStatus::Demo->value),
                'version_id' => null,
            ])
            ->all();
    }
}
