<?php

namespace App\Services\Setup\Datasets;

use App\Enums\Setup\Ownership;
use App\Models\Ai\ModelPrice;
use App\Services\Ai\ModelPrices;
use App\Services\Setup\AbstractSetupDataset;

/**
 * Moves the prices that used to live only in `config/billing.php` into the
 * `model_prices` table, where a super admin can maintain them. Safe to run
 * again: it never overwrites a price that is already there.
 */
class ModelPricesDataset extends AbstractSetupDataset
{
    public function key(): string
    {
        return 'model-prices';
    }

    public function label(): string
    {
        return 'Model prices';
    }

    public function ownership(): Ownership
    {
        return Ownership::Shared;
    }

    public function dependencies(): array
    {
        return ['model-catalog'];
    }

    public function rows(): int
    {
        return ModelPrice::query()->count();
    }

    protected function dataFiles(): array
    {
        return [config_path('billing.php')];
    }

    public function apply(): void
    {
        app(ModelPrices::class)->importFromConfig();
    }
}
