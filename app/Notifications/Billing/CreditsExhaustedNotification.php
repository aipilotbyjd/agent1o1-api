<?php

namespace App\Notifications\Billing;

use App\Enums\Notifications\NotificationEvent;
use App\Models\Workspaces\Workspace;
use App\Notifications\Workspace\WorkspaceEventNotification;

/**
 * The customer-facing sibling of `CreditGate`'s `usage.limit_exhausted`
 * admin alert: the workspace is actually being refused new work, so unlike
 * `CreditsLowNotification` this is the "you are blocked now" message.
 */
class CreditsExhaustedNotification extends WorkspaceEventNotification
{
    public function __construct(Workspace $workspace)
    {
        parent::__construct(
            workspace: $workspace,
            event: NotificationEvent::CreditsExhausted,
            title: "{$workspace->name} is out of credits",
            body: 'New workflow runs and agent turns are being refused until the plan allowance resets or top-up credits are purchased.',
            data: [
                'workspace_id' => $workspace->id,
            ],
        );
    }
}
