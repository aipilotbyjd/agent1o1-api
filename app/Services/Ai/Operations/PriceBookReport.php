<?php

namespace App\Services\Ai\Operations;

use App\Enums\Ai\ModelPriceStatus;
use App\Enums\Ai\PriceEnforcement;
use App\Models\Ai\AiCallEvent;
use App\Models\Ai\ModelPrice;
use App\Models\Ai\ModelRoute;
use App\Services\Ai\ModelCatalogResolver;
use App\Services\Ai\ModelPrices;

/**
 * Whether every route the platform pays for can be billed on a verified
 * price: each enabled route of an active model, how far its price can be
 * trusted, and how many calls and dollars went through it on the platform's
 * key in the last `$days` days. `would_block` marks the routes `enforce`
 * would refuse, so price enforcement can be switched on knowing exactly
 * what changes.
 */
class PriceBookReport
{
    public function __construct(private readonly ModelPrices $prices) {}

    /**
     * @return array{enforcement: string, days: int, summary: array{routes: int, verified: int, unverified: int, demo: int, unpriced: int, would_block: int, ready_to_enforce: bool, untrusted_calls: int, untrusted_cost_usd: float}, routes: list<array<string, mixed>>}
     */
    public function report(int $days = 7): array
    {
        $usage = AiCallEvent::query()
            ->where('occurred_at', '>=', now()->subDays($days))
            ->where('is_byok', false)
            ->selectRaw('provider, model, count(*) as calls, sum(provider_cost_usd) as cost')
            ->groupBy('provider', 'model')
            ->get()
            ->keyBy(fn ($row): string => "{$row->provider}:{$row->model}");

        $stored = ModelPrice::query()->get()->keyBy(fn (ModelPrice $price): string => "{$price->provider}:{$price->model_id}");

        $routes = ModelRoute::query()
            ->with('modelCatalog:id,slug,is_active')
            ->where('is_enabled', true)
            ->orderBy('model_catalog_id')
            ->orderBy('priority')
            ->get()
            ->filter(fn (ModelRoute $route): bool => (bool) $route->modelCatalog?->is_active)
            ->map(function (ModelRoute $route) use ($usage, $stored): array {
                $key = "{$route->execution_provider}:{$route->execution_model_id}";
                $status = $this->prices->status($route->execution_provider, $route->execution_model_id);
                $platformRuns = ModelCatalogResolver::providerIsConfigured($route->execution_provider, ignoringPause: true);
                $price = $this->prices->for($route->execution_provider, $route->execution_model_id);

                return [
                    'model' => $route->modelCatalog->slug,
                    'provider' => $route->execution_provider,
                    'model_id' => $route->execution_model_id,
                    'priority' => $route->priority,
                    'platform_pays' => $platformRuns,
                    'price_id' => $stored[$key]->id ?? null,
                    'price_status' => $status?->value ?? 'unpriced',
                    'input_per_mtok' => $price['input'] ?? null,
                    'output_per_mtok' => $price['output'] ?? null,
                    'checked_at' => $stored[$key]->checked_at ?? null,
                    'verified_at' => $stored[$key]->verified_at ?? null,
                    'calls' => (int) ($usage[$key]->calls ?? 0),
                    'cost_usd' => round((float) ($usage[$key]->cost ?? 0), 4),
                    'would_block' => $platformRuns && $status !== ModelPriceStatus::Verified,
                ];
            })
            ->values();

        $untrusted = $routes->where('price_status', '!=', ModelPriceStatus::Verified->value);

        return [
            'enforcement' => PriceEnforcement::current()->value,
            'days' => $days,
            'summary' => [
                'routes' => $routes->count(),
                'verified' => $routes->where('price_status', ModelPriceStatus::Verified->value)->count(),
                'unverified' => $routes->where('price_status', ModelPriceStatus::Unverified->value)->count(),
                'demo' => $routes->where('price_status', ModelPriceStatus::Demo->value)->count(),
                'unpriced' => $routes->where('price_status', 'unpriced')->count(),
                'would_block' => $routes->where('would_block', true)->count(),
                'ready_to_enforce' => $routes->where('would_block', true)->isEmpty(),
                'untrusted_calls' => (int) $untrusted->sum('calls'),
                'untrusted_cost_usd' => round((float) $untrusted->sum('cost_usd'), 4),
            ],
            'routes' => $routes->all(),
        ];
    }
}
