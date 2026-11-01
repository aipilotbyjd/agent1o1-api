<?php

namespace App\Notifications\Billing;

use App\Enums\Notifications\NotificationEvent;
use App\Models\Workspaces\Workspace;
use App\Notifications\Workspace\WorkspaceEventNotification;

/**
 * Gumloop's Credit Usage Notification: "an email when usage crosses a
 * threshold", at whichever of the workspace's own thresholds this charge
 * crossed.
 *
 * `$thresholdPercent` is the line that was crossed and `$percentUsed` is
 * where the balance actually landed; they differ whenever one charge jumps
 * well past a threshold, and saying both is what makes the message
 * actionable rather than alarming.
 */
class CreditsLowNotification extends WorkspaceEventNotification
{
    public function __construct(
        Workspace $workspace,
        int $creditsUsed,
        int $creditsLimit,
        int $percentUsed,
        ?int $thresholdPercent = null,
    ) {
        $thresholdPercent ??= $percentUsed;

        parent::__construct(
            workspace: $workspace,
            event: NotificationEvent::CreditsLow,
            title: "{$workspace->name} has used {$thresholdPercent}% of its credits",
            body: "{$creditsUsed} of {$creditsLimit} credits used this period ({$percentUsed}%). Consider a top-up or an upgrade before you run out.",
            data: [
                'workspace_id' => $workspace->id,
                'credits_used' => $creditsUsed,
                'credits_limit' => $creditsLimit,
                'percent_used' => $percentUsed,
                'threshold_percent' => $thresholdPercent,
            ],
        );
    }
}
