<?php

namespace App\Console\Commands\Admin;

use App\Services\Ai\Operations\ModelPriceSync;
use Illuminate\Console\Command;
use Throwable;

/**
 * Fills missing, demo and unverified model prices from the providers' list
 * prices (as `unverified`, for an operator to confirm), and alerts when a
 * verified price has drifted from the list. Never changes a verified price.
 */
class ModelPricesSyncCommand extends Command
{
    protected $signature = 'model-prices:sync {--tolerance=0.02 : How far a verified price may differ from the list before it is reported}';

    protected $description = 'Fills unverified model prices from the providers\' list prices and reports drift on verified ones.';

    public function handle(ModelPriceSync $sync): int
    {
        try {
            $result = $sync->sync((float) $this->option('tolerance'));
        } catch (Throwable $e) {
            report($e);
            $this->error('Could not read the price list: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Matched {$result['observed']} model(s): {$result['created']} price(s) created, {$result['updated']} updated, ".count($result['drift']).' drifted.');

        foreach ($result['drift'] as $drift) {
            $this->warn(sprintf(
                '%s:%s verified $%s / $%s, list $%s / $%s per 1M tokens',
                $drift['provider'], $drift['model_id'],
                $drift['verified']['input'], $drift['verified']['output'],
                $drift['list']['input'], $drift['list']['output'],
            ));
        }

        return self::SUCCESS;
    }
}
