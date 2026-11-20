<?php

namespace App\Notifications\Billing;

use App\Enums\Notifications\NotificationType;
use App\Models\Workspaces\Workspace;
use App\Notifications\AppNotification;

/**
 * Overage's own "you are blocked now" message. Distinct from
 * `CreditsExhaustedNotification` because the remedy is different: the
 * allowance is gone *and* the overage the workspace agreed to pay for is
 * spent, so the fix is to raise the cap (or upgrade), not to wait for the
 * period to reset: agents stop once the cap is reached.
 */
class OverageCapReachedNotification extends AppNotification
{
    public function __construct(Workspace $workspace, int $overageCreditsUsed, int $overageCreditsLimit)
    {
        parent::__construct(
            workspace: $workspace,
            type: NotificationType::OverageCapReached,
            title: "{$workspace->name} has reached its credit overage cap",
            body: "All {$overageCreditsLimit} overage credits for this billing period are spent. New workflow runs and agent turns are being refused until the cap is raised or the period resets.",
            data: [
                'workspace_id' => $workspace->id,
                'overage_credits_used' => $overageCreditsUsed,
                'overage_credits_limit' => $overageCreditsLimit,
            ],
        );
    }
}
