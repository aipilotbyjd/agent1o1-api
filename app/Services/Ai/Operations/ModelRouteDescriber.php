<?php

namespace App\Services\Ai\Operations;

use App\Models\Ai\AiEndpoint;
use App\Models\Ai\ModelPrice;
use App\Models\Ai\ModelRoute;
use App\Services\Ai\ModelCatalogResolver;
use App\Services\Ai\ModelPrices;
use Illuminate\Support\Carbon;

/**
 * One route as an operator reads it: what serves it, whether the platform can
 * run it, what it costs and how it last tested. The endpoint names and price
 * rows are read once per instance, so describing a whole catalog costs two
 * queries rather than two per route. Never exposes a key.
 */
class ModelRouteDescriber
{
    /** @var array<int, string>|null */
    private ?array $endpointSlugs = null;

    /** @var array<string, ModelPrice>|null */
    private ?array $priceRows = null;

    public function __construct(private readonly ModelPrices $prices) {}

    /**
     * @return array<string, mixed>
     */
    public function describe(ModelRoute $route): array
    {
        $provider = $route->execution_provider;
        $configured = ModelCatalogResolver::providerIsConfigured($provider);
        $byokCapable = config("byok.providers.{$provider}") !== null;

        return [
            'id' => $route->id,
            'provider' => $provider,
            'model_id' => $route->execution_model_id,
            'priority' => $route->priority,
            'is_enabled' => $route->is_enabled,
            'kind' => match (true) {
                in_array($provider, $this->endpointSlugs(), true) => 'endpoint',
                $configured => 'platform',
                $byokCapable => 'own_key_only',
                default => 'unconfigured',
            },
            'platform_can_run' => $configured,
            'byok_capable' => $byokCapable,
            'price' => $this->price($provider, $route->execution_model_id),
            'failure_count' => $route->failure_count,
            'last_failed_at' => $route->last_failed_at,
            'last_checked_at' => $route->last_checked_at,
            'last_check_ok' => $route->last_check_ok,
            'last_check_latency_ms' => $route->last_check_latency_ms,
            'last_check_message' => $route->last_check_message,
        ];
    }

    /**
     * @return array{input_per_mtok: float, output_per_mtok: float, source: string, checked_at: ?Carbon, in_table: bool}|null
     */
    private function price(string $provider, string $model): ?array
    {
        $row = $this->priceRows()["{$provider}:{$model}"] ?? null;

        if ($row !== null) {
            return [
                'input_per_mtok' => $row->input_per_mtok,
                'output_per_mtok' => $row->output_per_mtok,
                'source' => $row->source->value,
                'checked_at' => $row->checked_at,
                'in_table' => true,
            ];
        }

        $legacy = $this->prices->for($provider, $model);

        return $legacy === null ? null : [
            'input_per_mtok' => $legacy['input'],
            'output_per_mtok' => $legacy['output'],
            'source' => 'config',
            'checked_at' => null,
            'in_table' => false,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function endpointSlugs(): array
    {
        return $this->endpointSlugs ??= AiEndpoint::query()->pluck('slug')->all();
    }

    /**
     * @return array<string, ModelPrice>
     */
    private function priceRows(): array
    {
        return $this->priceRows ??= ModelPrice::query()->get()
            ->keyBy(fn (ModelPrice $price): string => "{$price->provider}:{$price->model_id}")
            ->all();
    }
}
