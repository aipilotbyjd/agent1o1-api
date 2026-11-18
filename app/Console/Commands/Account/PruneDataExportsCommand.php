<?php

namespace App\Console\Commands\Account;

use App\Services\Account\DataExportService;
use Illuminate\Console\Command;

/**
 * Deletes the archive of every personal data export past its download
 * window. The row is kept, marked expired, so the account's export history
 * still shows what was requested and when.
 */
class PruneDataExportsCommand extends Command
{
    protected $signature = 'account:prune-data-exports';

    protected $description = 'Delete personal data export archives past their download window.';

    public function handle(DataExportService $exports): int
    {
        $count = $exports->pruneExpired();

        $this->info("Expired {$count} data export(s).");

        return self::SUCCESS;
    }
}
