<?php

namespace App\Notifications\Referrals;

use App\Enums\Notifications\NotificationEvent;
use App\Models\Billing\PlanGrant;
use App\Models\Workspaces\Workspace;
use App\Notifications\Workspace\WorkspaceEventNotification;

class ReferralPlanTimeEndingNotification extends WorkspaceEventNotification
{
    public function __construct(Workspace $workspace, PlanGrant $grant)
    {
        $planName = $grant->plan?->name ?? 'your plan';
        $endsOn = $grant->expires_at?->toFormattedDateString();

        parent::__construct(
            workspace: $workspace,
            event: NotificationEvent::ReferralPlanTimeEnding,
            title: "Your free {$planName} time ends {$endsOn}",
            body: "The {$planName} time you earned through referrals runs out on {$endsOn}. Subscribe to keep it, or refer more friends to earn more.",
            data: [
                'plan_grant_id' => $grant->id,
                'expires_at' => $grant->expires_at?->toIso8601String(),
            ],
        );
    }
}
