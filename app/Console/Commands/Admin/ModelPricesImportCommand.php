<?php

namespace App\Console\Commands\Admin;

use App\Services\Ai\ModelPrices;
use Illuminate\Console\Command;

/**
 * Copies the prices that still live in `config/billing.php` into the
 * `model_prices` table. Same as the seeder, for an environment where seeders
 * are not run. Never overwrites a price already in the table.
 */
class ModelPricesImportCommand extends Command
{
    protected $signature = 'model-prices:import-config';

    protected $description = 'Copies the model prices in config/billing.php into the model_prices table.';

    public function handle(ModelPrices $prices): int
    {
        $created = $prices->importFromConfig();

        $this->info($created === 0 ? 'Nothing to import: every config price is already in the table.' : "Imported {$created} price(s).");

        return self::SUCCESS;
    }
}
