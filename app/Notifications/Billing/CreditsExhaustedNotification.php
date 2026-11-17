<?php

namespace App\Notifications\Billing;

use App\Enums\Notifications\NotificationType;
use App\Models\Workspaces\Workspace;
use App\Notifications\AppNotification;

/**
 * The customer-facing sibling of `CreditGate`'s `usage.limit_exhausted`
 * admin alert: the workspace is actually being refused new work, so unlike
 * `CreditsLowNotification` this is the "you are blocked now" message.
 */
class CreditsExhaustedNotification extends AppNotification
{
    public function __construct(Workspace $workspace)
    {
        parent::__construct(
            workspace: $workspace,
            type: NotificationType::CreditsExhausted,
            title: "{$workspace->name} is out of credits",
            body: 'New workflow runs and agent turns are being refused until the plan allowance resets or top-up credits are purchased.',
            data: [
                'workspace_id' => $workspace->id,
            ],
        );
    }
}
