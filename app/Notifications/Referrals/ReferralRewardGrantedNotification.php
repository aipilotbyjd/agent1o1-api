<?php

namespace App\Notifications\Referrals;

use App\Enums\Notifications\NotificationEvent;
use App\Models\Referrals\ReferralReward;
use App\Models\Workspaces\Workspace;
use App\Notifications\Workspace\WorkspaceEventNotification;
use App\Services\Referrals\ReferralRuleDescriber;

class ReferralRewardGrantedNotification extends WorkspaceEventNotification
{
    public function __construct(Workspace $workspace, ReferralReward $reward)
    {
        $summary = app(ReferralRuleDescriber::class)->rewardSummary($reward);

        parent::__construct(
            workspace: $workspace,
            event: NotificationEvent::ReferralRewardGranted,
            title: "Referral reward: {$summary}",
            body: "{$summary} has been added to {$workspace->name}.",
            data: [
                'reward_id' => $reward->id,
                'reward_type' => $reward->reward_type->value,
            ],
        );
    }
}
