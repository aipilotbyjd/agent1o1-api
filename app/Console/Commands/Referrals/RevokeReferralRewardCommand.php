<?php

namespace App\Console\Commands\Referrals;

use App\Enums\Referrals\ReferralRewardStatus;
use App\Models\Referrals\ReferralReward;
use App\Services\Referrals\ReferralAdmin;
use Illuminate\Console\Command;

class RevokeReferralRewardCommand extends Command
{
    protected $signature = 'referrals:revoke {reward : The reward id} {--reason= : Why it is being revoked}';

    protected $description = 'Revokes a referral reward, clawing back what it gave where possible.';

    public function handle(ReferralAdmin $admin): int
    {
        $reward = ReferralReward::query()->find($this->argument('reward'));

        if ($reward === null) {
            $this->error('No reward has that id.');

            return self::FAILURE;
        }

        if ($reward->status === ReferralRewardStatus::Revoked) {
            $this->info('That reward is already revoked.');

            return self::SUCCESS;
        }

        $admin->revokeReward($reward, $this->option('reason') ?? 'Revoked by an admin', null);

        $this->info("Revoked reward {$reward->id}.");

        return self::SUCCESS;
    }
}
