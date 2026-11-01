<?php

namespace App\Services\Setup\Datasets;

use App\Enums\Setup\Ownership;
use App\Models\Ai\ModelCatalog;
use App\Services\Setup\AbstractSetupDataset;

/**
 * The public model catalog shown to end users, current as of the August
 * 2026 model landscape (researched via web search: see the per-brand
 * comments below for what's confirmed vs. best-effort).
 *
 * Each entry's primary route is on whichever direct provider your `.env`
 * already has keys for. Secondary/aggregator routes (OpenRouter, Fireworks,
 * Together, Bedrock) are seeded disabled: turn one on once its
 * credential/API key is configured (see `php artisan model-catalog:add-route`
 * or `App\Models\Ai\ModelRoute`) to give that catalog entry real failover.
 *
 * Open-weight aggregator model ids (Kimi K3, GLM-5.2, MiniMax M3, Qwen3.6,
 * Llama 4 Maverick) are best-effort slugs inferred from provider naming
 * conventions, not confirmed against Fireworks/Together/OpenRouter's own
 * catalogs: verify the exact string in that provider's docs before
 * enabling one of these routes.
 */
class ModelCatalogDataset extends AbstractSetupDataset
{
    public function key(): string
    {
        return 'model-catalog';
    }

    public function label(): string
    {
        return 'Model catalog';
    }

    public function ownership(): Ownership
    {
        return Ownership::Shared;
    }

    public function rows(): int
    {
        return ModelCatalog::query()->count();
    }

    public function apply(): void
    {
        $definitions = $this->definitions();

        foreach ($definitions['models'] as $model) {
            $this->entry($model, $definitions['details'][$model['slug']] ?? []);
        }
    }

    /**
     * @return array{models: list<array<string, mixed>>, details: array<string, array<string, mixed>>}
     */
    private function definitions(): array
    {
        return require database_path('setup/model-catalog.php');
    }

    /**
     * The code sets a model's identity and descriptive details. Whether it is
     * active, and each route's priority and enabled flag, belong to the admin
     * once the row exists, so a re-sync never flips them back.
     *
     * @param  array<string, mixed>  $model
     * @param  array<string, mixed>  $details
     */
    private function entry(array $model, array $details): void
    {
        $catalog = ModelCatalog::query()->firstOrNew(['slug' => $model['slug']]);

        $catalog->fill(['display_name' => $model['display_name'], 'brand' => $model['brand'], 'capabilities' => $model['capabilities'], 'is_internal' => $model['internal'] ?? false, ...$details]);

        if (! $catalog->exists) {
            $catalog->forceFill(['is_active' => true]);
        }

        $catalog->save();

        foreach ($model['routes'] as $route) {
            $catalog->routes()->firstOrCreate(
                ['execution_provider' => $route['execution_provider'], 'execution_model_id' => $route['execution_model_id']],
                $route,
            );
        }
    }

    public function problems(): array
    {
        $definitions = $this->definitions();
        $problems = [];

        foreach ($this->duplicates(array_column($definitions['models'], 'slug')) as $slug) {
            $problems[] = "Model [{$slug}] appears more than once.";
        }

        foreach ($definitions['models'] as $model) {
            $slug = $model['slug'];
            $routes = $model['routes'] ?? [];

            if ($routes === []) {
                $problems[] = "Model [{$slug}] has no routes.";
            }

            $pairs = array_map(fn (array $route): string => $route['execution_provider'].'/'.$route['execution_model_id'], $routes);

            foreach ($this->duplicates($pairs) as $pair) {
                $problems[] = "Model [{$slug}] lists the route [{$pair}] twice.";
            }
        }

        foreach (array_diff(array_keys($definitions['details']), array_column($definitions['models'], 'slug')) as $slug) {
            $problems[] = "Details exist for [{$slug}], which is not a model.";
        }

        return $problems;
    }

    protected function dataFiles(): array
    {
        return [database_path('setup/model-catalog.php')];
    }
}
