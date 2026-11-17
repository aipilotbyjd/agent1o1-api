<?php

namespace App\Notifications\Referrals;

use App\Enums\Notifications\NotificationType;
use App\Models\Referrals\ReferralReward;
use App\Models\Workspaces\Workspace;
use App\Notifications\AppNotification;
use App\Services\Referrals\ReferralRuleDescriber;

class ReferralRewardGrantedNotification extends AppNotification
{
    public function __construct(Workspace $workspace, ReferralReward $reward)
    {
        $summary = app(ReferralRuleDescriber::class)->rewardSummary($reward);

        parent::__construct(
            workspace: $workspace,
            type: NotificationType::ReferralRewardGranted,
            title: "Referral reward: {$summary}",
            body: "{$summary} has been added to {$workspace->name}.",
            data: [
                'reward_id' => $reward->id,
                'reward_type' => $reward->reward_type->value,
            ],
        );
    }
}
