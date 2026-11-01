<?php

namespace App\Services\Referrals;

use App\Enums\Referrals\ReferralRewardStatus;
use App\Enums\Referrals\ReferralRewardType;
use App\Models\Referrals\ReferralReward;
use App\Models\Workspaces\Workspace;

/**
 * Extra trial days a workspace has earned through `trial_extension`
 * rewards, added to the plan's own trial by `CheckoutSubscriptionAction`.
 */
class ReferralTrialBonus
{
    public function daysFor(Workspace $workspace): int
    {
        if (! config('referrals.enabled')) {
            return 0;
        }

        return (int) ReferralReward::query()
            ->where('workspace_id', $workspace->id)
            ->where('reward_type', ReferralRewardType::TrialExtension)
            ->where('status', ReferralRewardStatus::Granted)
            ->sum('trial_days');
    }
}
