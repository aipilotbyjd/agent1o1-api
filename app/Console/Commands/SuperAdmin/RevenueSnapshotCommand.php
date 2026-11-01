<?php

namespace App\Console\Commands\SuperAdmin;

use App\Models\SuperAdmin\RevenueDailySnapshot;
use App\Services\SuperAdmin\Revenue\RevenueMetrics;
use App\Services\SuperAdmin\Revenue\SubscriptionLedger;
use Illuminate\Console\Command;

/**
 * Scheduled nightly: stores today's subscribers and MRR per plan so revenue
 * history survives later plan price changes. Safe to re-run: a day's rows are
 * replaced, not duplicated.
 */
class RevenueSnapshotCommand extends Command
{
    protected $signature = 'revenue:snapshot';

    protected $description = 'Stores today\'s subscribers and MRR per plan.';

    public function handle(RevenueMetrics $revenue, SubscriptionLedger $ledger): int
    {
        $rows = $revenue->snapshotRows();
        $date = today()->toDateString();

        foreach ($rows as $row) {
            RevenueDailySnapshot::query()->updateOrCreate(
                ['date' => $date, 'plan_slug' => $row['plan'], 'currency' => $ledger->currency()],
                ['subscribers' => $row['subscribers'], 'mrr' => $row['mrr']],
            );
        }

        $this->info('Stored '.count($rows)." plan snapshot(s) for {$date}.");

        return self::SUCCESS;
    }
}
