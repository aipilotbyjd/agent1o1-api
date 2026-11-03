<?php

namespace App\Services\Ai\Operations;

use App\Enums\Ai\ModelPriceSource;
use App\Enums\Ai\ModelPriceStatus;
use App\Models\Ai\ModelCatalog;
use App\Models\Ai\ModelPrice;
use App\Services\SuperAdmin\Alerts\SuperAdminAlerter;
use Illuminate\Support\Facades\Http;

/**
 * Keeps the price book close to the providers' list prices without ever
 * trusting them blindly. Reads OpenRouter's public model list (one feed for
 * most models), and for every catalog model that has an OpenRouter route:
 *
 * - a route with no price, or only a demo or unverified one, gets the list
 *   rates as an `unverified` price, for an operator to confirm;
 * - a route with a verified price is never changed; if it differs from the
 *   list by more than `$tolerance`, it is reported as drift and an alert is
 *   raised, since either the provider changed its prices or the contract
 *   rate needs a second look.
 */
class ModelPriceSync
{
    public const string FEED_URL = 'https://openrouter.ai/api/v1/models';

    public function __construct(private readonly SuperAdminAlerter $alerter) {}

    /**
     * @return array{observed: int, created: int, updated: int, drift: list<array{provider: string, model_id: string, verified: array{input: float, output: float}, list: array{input: float, output: float}}>}
     */
    public function sync(float $tolerance = 0.02): array
    {
        $feed = $this->feed();
        $result = ['observed' => 0, 'created' => 0, 'updated' => 0, 'drift' => []];

        foreach (ModelCatalog::query()->with('routes')->get() as $model) {
            $listed = $model->routes
                ->where('execution_provider', 'openrouter')
                ->map(fn ($route) => $feed[$route->execution_model_id] ?? null)
                ->filter()
                ->first();

            if ($listed === null) {
                continue;
            }

            $result['observed']++;

            foreach ($model->routes as $route) {
                $price = ModelPrice::query()
                    ->where('provider', $route->execution_provider)
                    ->where('model_id', $route->execution_model_id)
                    ->first();

                if ($price?->isVerified()) {
                    if ($this->drifted($price, $listed, $tolerance)) {
                        $result['drift'][] = [
                            'provider' => $price->provider,
                            'model_id' => $price->model_id,
                            'verified' => ['input' => $price->input_per_mtok, 'output' => $price->output_per_mtok],
                            'list' => ['input' => $listed['input'], 'output' => $listed['output']],
                        ];
                    }

                    continue;
                }

                $attributes = [
                    'input_per_mtok' => $listed['input'],
                    'output_per_mtok' => $listed['output'],
                    'cache_read_per_mtok' => $listed['cache_read'],
                    'cache_write_per_mtok' => $listed['cache_write'],
                    'source' => ModelPriceSource::ProviderList,
                    'status' => ModelPriceStatus::Unverified,
                    'checked_at' => now(),
                    'notes' => 'List price from OpenRouter on '.now()->toDateString().': confirm against the provider, then verify.',
                ];

                if ($price === null) {
                    ModelPrice::query()->create(['provider' => $route->execution_provider, 'model_id' => $route->execution_model_id, ...$attributes]);
                    $result['created']++;
                } else {
                    $price->fill($attributes)->save();
                    $result['updated']++;
                }
            }
        }

        if ($result['drift'] !== []) {
            $this->alerter->raise('model_price_drift', sprintf(
                '%d verified model price(s) differ from the providers\' list prices by more than %d%%.',
                count($result['drift']),
                (int) round($tolerance * 100),
            ), ['drift' => $result['drift']]);
        }

        return $result;
    }

    /**
     * OpenRouter's list, keyed by its model id, in US dollars per million
     * tokens.
     *
     * @return array<string, array{input: float, output: float, cache_read: float|null, cache_write: float|null}>
     */
    public function feed(): array
    {
        $models = Http::timeout(20)->acceptJson()->get(config('model_operations.price_sync.feed_url', self::FEED_URL))->throw()->json('data', []);
        $feed = [];

        foreach ((array) $models as $model) {
            $pricing = $model['pricing'] ?? null;

            if (! isset($model['id'], $pricing['prompt'], $pricing['completion']) || ! is_numeric($pricing['prompt']) || ! is_numeric($pricing['completion'])) {
                continue;
            }

            $feed[(string) $model['id']] = [
                'input' => round((float) $pricing['prompt'] * 1_000_000, 6),
                'output' => round((float) $pricing['completion'] * 1_000_000, 6),
                'cache_read' => is_numeric($pricing['input_cache_read'] ?? null) ? round((float) $pricing['input_cache_read'] * 1_000_000, 6) : null,
                'cache_write' => is_numeric($pricing['input_cache_write'] ?? null) ? round((float) $pricing['input_cache_write'] * 1_000_000, 6) : null,
            ];
        }

        return $feed;
    }

    /**
     * @param  array{input: float, output: float}  $listed
     */
    private function drifted(ModelPrice $price, array $listed, float $tolerance): bool
    {
        $differs = fn (float $ours, float $theirs): bool => abs($ours - $theirs) > max($ours, $theirs) * $tolerance;

        return $differs($price->input_per_mtok, $listed['input']) || $differs($price->output_per_mtok, $listed['output']);
    }
}
