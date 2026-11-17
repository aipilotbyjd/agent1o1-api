<?php

namespace App\Notifications\Referrals;

use App\Enums\Notifications\NotificationType;
use App\Models\Workspaces\Workspace;
use App\Notifications\AppNotification;

class ReferralMilestoneReachedNotification extends AppNotification
{
    public function __construct(Workspace $workspace, int $convertedReferrals)
    {
        parent::__construct(
            workspace: $workspace,
            type: NotificationType::ReferralMilestoneReached,
            title: "You reached {$convertedReferrals} paying referrals",
            body: 'Your milestone bonus is on its way.',
            data: ['converted_referrals' => $convertedReferrals],
        );
    }
}
