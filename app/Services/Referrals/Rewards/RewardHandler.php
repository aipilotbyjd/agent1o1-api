<?php

namespace App\Services\Referrals\Rewards;

use App\Models\Referrals\ReferralReward;

/**
 * Applies and withdraws one kind of referral reward. Handlers run inside
 * the grant/revoke action's transaction and may update the reward's own
 * value columns (e.g. the days actually granted after a cap), but never its
 * status: `GrantReferralRewardAction`/`RevokeReferralRewardAction` own
 * that.
 */
interface RewardHandler
{
    public function grant(ReferralReward $reward): void;

    public function revoke(ReferralReward $reward): void;
}
