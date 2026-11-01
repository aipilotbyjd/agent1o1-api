<?php

namespace App\Console\Commands\Admin;

use App\Models\Ai\ModelCatalog;
use App\Services\Ai\ModelCatalogResolver;
use Illuminate\Console\Command;

/**
 * Adds (or updates) an execution backend for a `ModelCatalog` entry:
 * there's no HTTP endpoint for this since it's a platform-level decision
 * (which backends actually run a public model), not something scoped to a
 * workspace. See `Services\Ai\ModelCatalogResolver`.
 */
class ModelCatalogAddRouteCommand extends Command
{
    protected $signature = 'model-catalog:add-route
        {slug : The model_catalog.slug to add a route to}
        {provider : A Lab provider name or a config/ai.php provider key (e.g. fireworks, together, openrouter)}
        {model : The model id that provider expects}
        {--priority=0 : Lower runs first}
        {--disabled : Seed the route disabled}';

    protected $description = 'Adds or updates an execution backend for a model catalog entry.';

    public function handle(ModelCatalogResolver $resolver): int
    {
        $catalog = ModelCatalog::query()->where('slug', $this->argument('slug'))->first();

        if ($catalog === null) {
            $this->error("No model catalog entry with slug [{$this->argument('slug')}].");

            return self::FAILURE;
        }

        $route = $catalog->routes()->updateOrCreate(
            [
                'execution_provider' => $this->argument('provider'),
                'execution_model_id' => $this->argument('model'),
            ],
            [
                'priority' => (int) $this->option('priority'),
                'is_enabled' => ! $this->option('disabled'),
            ],
        );

        $resolver->forget($catalog->slug);

        $this->info("Route #{$route->id} ({$route->execution_provider}:{$route->execution_model_id}) saved for [{$catalog->slug}], priority {$route->priority}, ".
            ($route->is_enabled ? 'enabled' : 'disabled').'.');

        return self::SUCCESS;
    }
}
