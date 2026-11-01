<?php

namespace App\Console\Commands\Referrals;

use App\Models\Referrals\ReferralVisit;
use Illuminate\Console\Command;

class PruneReferralVisitsCommand extends Command
{
    protected $signature = 'referrals:prune-visits';

    protected $description = 'Deletes referral link visits older than the configured retention.';

    public function handle(): int
    {
        $deleted = ReferralVisit::query()
            ->where('created_at', '<', now()->subDays((int) config('referrals.visit_retention_days', 90)))
            ->delete();

        $this->info("Deleted {$deleted} old referral visit(s).");

        return self::SUCCESS;
    }
}
