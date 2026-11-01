<?php

namespace App\Notifications\Referrals;

use App\Enums\Notifications\NotificationEvent;
use App\Models\Workspaces\Workspace;
use App\Notifications\Workspace\WorkspaceEventNotification;

class ReferralMilestoneReachedNotification extends WorkspaceEventNotification
{
    public function __construct(Workspace $workspace, int $convertedReferrals)
    {
        parent::__construct(
            workspace: $workspace,
            event: NotificationEvent::ReferralMilestoneReached,
            title: "You reached {$convertedReferrals} paying referrals",
            body: 'Your milestone bonus is on its way.',
            data: ['converted_referrals' => $convertedReferrals],
        );
    }
}
