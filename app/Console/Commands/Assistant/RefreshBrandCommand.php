<?php

namespace App\Console\Commands\Assistant;

use App\Events\Assistant\BrandChanged;
use App\Services\Assistant\Branding\BrandRepository;
use Illuminate\Console\Command;

/**
 * Run after a rename (and after `config:cache`): tells every open frontend
 * to refetch the brand so the new name appears without a reload.
 */
class RefreshBrandCommand extends Command
{
    protected $signature = 'assistant:brand-refresh';

    protected $description = 'Broadcast the current assistant brand to open frontends.';

    public function handle(BrandRepository $brands): int
    {
        $brand = $brands->current();

        BrandChanged::dispatch($brand);

        $this->info("Broadcast brand \"{$brand->name}\".");

        return self::SUCCESS;
    }
}
