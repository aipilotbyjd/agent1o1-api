<?php

namespace App\Console\Commands\Admin;

use App\Models\Ai\ModelCatalog;
use Illuminate\Console\Command;

/**
 * Shows every public `ModelCatalog` entry and the execution routes behind
 * it, in resolution order: the operator's view of what's hidden from end
 * users behind each catalog slug. See `Services\Ai\ModelCatalogResolver`.
 */
class ModelCatalogListCommand extends Command
{
    protected $signature = 'model-catalog:list';

    protected $description = 'Lists each model catalog entry and its execution routes, in priority order.';

    public function handle(): int
    {
        $catalog = ModelCatalog::query()->with('routes')->orderBy('sort_order')->get();

        if ($catalog->isEmpty()) {
            $this->warn('No model catalog entries. Run `php artisan setup:catalog:sync --only=model-catalog`.');

            return self::SUCCESS;
        }

        foreach ($catalog as $entry) {
            $this->line("<fg=cyan;options=bold>{$entry->slug}</> ({$entry->display_name}, {$entry->brand}): ".
                ($entry->is_active ? 'active' : 'inactive'));

            if ($entry->routes->isEmpty()) {
                $this->warn('  no routes configured: resolving this slug will fail');

                continue;
            }

            foreach ($entry->routes as $route) {
                $status = $route->is_enabled ? 'enabled' : 'disabled';
                $this->line("  [{$route->priority}] {$route->execution_provider}:{$route->execution_model_id}: {$status}".
                    ($route->failure_count > 0 ? " ({$route->failure_count} recent failures)" : ''));
            }
        }

        return self::SUCCESS;
    }
}
