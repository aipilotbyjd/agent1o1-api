<?php

namespace App\Console\Commands\Referrals;

use App\Actions\Referrals\GrantReferralRewardAction;
use App\Models\Referrals\ReferralReward;
use Illuminate\Console\Command;

/**
 * Grants rewards whose hold (`grant_after`) has passed. Rewards awaiting
 * an admin's approval are never touched here, and a reward revoked in the
 * meantime (refund, dispute, rejected referral) is no longer pending.
 */
class GrantPendingReferralRewardsCommand extends Command
{
    protected $signature = 'referrals:grant-pending';

    protected $description = 'Grants referral rewards whose hold period has passed.';

    public function handle(GrantReferralRewardAction $grant): int
    {
        $granted = 0;

        ReferralReward::query()->due()->chunkById(100, function ($rewards) use ($grant, &$granted): void {
            foreach ($rewards as $reward) {
                try {
                    if ($grant->execute($reward)) {
                        $granted++;
                    }
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        });

        $this->info("Granted {$granted} referral reward(s).");

        return self::SUCCESS;
    }
}
