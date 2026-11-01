<?php

namespace App\Console\Commands\Setup;

use App\Models\System\SetupSync;
use App\Services\Setup\SetupRegistry;
use App\Services\Setup\SetupSyncer;
use Illuminate\Console\Command;

class SetupCatalogStatusCommand extends Command
{
    protected $signature = 'setup:catalog:status';

    protected $description = 'Show whether each setup dataset is in sync with the code. Exits non-zero if any is not.';

    public function handle(SetupRegistry $registry, SetupSyncer $syncer): int
    {
        $synced = SetupSync::query()->pluck('synced_at', 'dataset');
        $allInSync = true;

        $rows = [];

        foreach ($registry->all() as $key => $dataset) {
            $state = $syncer->inspect($dataset);
            $allInSync = $allInSync && ! $state->needsSync();

            $rows[] = [
                $key,
                $dataset->ownership()->label(),
                "{$dataset->rows()} / ≥{$dataset->minimumRows()}",
                $state->value,
                $synced->get($key)?->diffForHumans() ?? 'never',
            ];
        }

        $this->table(['Dataset', 'Owner', 'Rows', 'State', 'Last synced'], $rows);

        if (! $allInSync) {
            $this->error('Setup data is not in sync. Run: php artisan setup:catalog:sync');
        }

        return $allInSync ? self::SUCCESS : self::FAILURE;
    }
}
