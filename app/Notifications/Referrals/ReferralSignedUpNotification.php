<?php

namespace App\Notifications\Referrals;

use App\Enums\Notifications\NotificationEvent;
use App\Models\Referrals\Referral;
use App\Models\Workspaces\Workspace;
use App\Notifications\Workspace\WorkspaceEventNotification;

/**
 * Tells a referrer someone joined with their link. Names nobody: the
 * referred person's identity is theirs to share, not ours.
 */
class ReferralSignedUpNotification extends WorkspaceEventNotification
{
    public function __construct(Workspace $workspace, Referral $referral)
    {
        parent::__construct(
            workspace: $workspace,
            event: NotificationEvent::ReferralSignedUp,
            title: 'Someone joined with your referral link',
            body: 'You will earn your referral rewards as they get started and subscribe.',
            data: ['referral_id' => $referral->id],
        );
    }
}
