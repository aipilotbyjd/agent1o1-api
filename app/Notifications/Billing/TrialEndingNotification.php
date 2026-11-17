<?php

namespace App\Notifications\Billing;

use App\Enums\Notifications\NotificationType;
use App\Models\Workspaces\Workspace;
use App\Notifications\AppNotification;
use Carbon\CarbonInterface;

/**
 * Sent once, a few days before `trial_ends_at`, so a workspace isn't
 * surprised by the first real charge. `billing:notify-trial-ending` is the
 * only caller and marks `trial_ending_notified_at` right after, so this
 * never fires twice for the same trial.
 */
class TrialEndingNotification extends AppNotification
{
    public function __construct(Workspace $workspace, CarbonInterface $trialEndsAt)
    {
        parent::__construct(
            workspace: $workspace,
            type: NotificationType::TrialEnding,
            title: "Your trial for {$workspace->name} ends soon",
            body: "Your trial ends on {$trialEndsAt->toFormattedDateString()}. After that, your card on file will be charged to continue on the paid plan.",
            data: [
                'workspace_id' => $workspace->id,
                'trial_ends_at' => $trialEndsAt->toIso8601String(),
            ],
        );
    }
}
