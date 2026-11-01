<?php

namespace App\Services\Referrals\Rewards;

use App\Models\Referrals\ReferralReward;

/**
 * Nothing to apply up front: a granted trial extension is read at checkout
 * by `ReferralTrialBonus`, which adds its days to the plan's own trial.
 * Revoking it (status alone) takes those days back off any future checkout.
 */
class TrialExtensionRewardHandler implements RewardHandler
{
    public function grant(ReferralReward $reward): void {}

    public function revoke(ReferralReward $reward): void {}
}
