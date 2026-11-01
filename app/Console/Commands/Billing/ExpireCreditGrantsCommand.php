<?php

namespace App\Console\Commands\Billing;

use App\Services\Billing\CreditWallet;
use Illuminate\Console\Command;

/**
 * Marks wallet grants past their expiry as expired, so the ledger shows
 * credits that lapsed. Spending never depends on it: an expired grant is
 * skipped the moment it expires, whether or not this has run.
 */
class ExpireCreditGrantsCommand extends Command
{
    protected $signature = 'credits:expire';

    protected $description = 'Marks credit grants past their expiry as expired.';

    public function handle(CreditWallet $wallet): int
    {
        $lapsed = $wallet->expireDue();

        $this->info("{$lapsed} credits expired.");

        return self::SUCCESS;
    }
}
