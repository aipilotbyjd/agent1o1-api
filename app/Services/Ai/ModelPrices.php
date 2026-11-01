<?php

namespace App\Services\Ai;

use App\Enums\Ai\ModelPriceSource;
use App\Models\Ai\ModelPrice;
use Illuminate\Support\Facades\Cache;

/**
 * What a backend model costs per million tokens: the `model_prices` table
 * first, then the older `config/billing.php` `model_prices` map, so prices
 * that were never moved into the table keep working. A model in neither is
 * unpriced, and `CreditMeter` bills it by a flat tokens-per-credit ratio.
 *
 * The table is cached for a few minutes as one map; saving or deleting a
 * `ModelPrice` drops the cache.
 */
class ModelPrices
{
    public const string CACHE_KEY = 'model-prices:all';

    private const int CACHE_TTL_SECONDS = 300;

    /**
     * @return array{input: float, output: float}|null US dollars per million tokens
     */
    public function for(?string $provider, ?string $model): ?array
    {
        if ($provider === null || $model === null) {
            return null;
        }

        $key = "{$provider}:{$model}";

        return $this->stored()[$key] ?? $this->legacy()[$key] ?? null;
    }

    /**
     * Whether `$provider`/`$model` has a price in the table, as opposed to
     * only in the legacy config map.
     */
    public function isStored(string $provider, string $model): bool
    {
        return isset($this->stored()["{$provider}:{$model}"]);
    }

    /**
     * Copies the legacy `config/billing.php` prices into the table (as
     * `config`-sourced rows), leaving any price already there alone.
     *
     * @return int how many rows were created
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
                    'source' => ModelPriceSource::Config,
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
    }

    /**
     * @return array<string, array{input: float, output: float}>
     */
    private function stored(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, fn (): array => ModelPrice::query()
            ->get()
            ->mapWithKeys(fn (ModelPrice $price): array => [
                "{$price->provider}:{$price->model_id}" => ['input' => $price->input_per_mtok, 'output' => $price->output_per_mtok],
            ])
            ->all());
    }

    /**
     * Read through `config()` as a whole array, never by dotted path: model
     * ids contain dots (`claude-haiku-4.5`), which a path lookup would split.
     *
     * @return array<string, array{input: float, output: float}>
     */
    private function legacy(): array
    {
        return collect((array) config('billing.model_prices', []))
            ->map(fn (array $price): array => ['input' => (float) $price['input'], 'output' => (float) $price['output']])
            ->all();
    }
}
