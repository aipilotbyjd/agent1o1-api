<?php

namespace App\Notifications\Billing;

use App\Enums\Notifications\NotificationType;
use App\Models\Workspaces\Workspace;
use App\Notifications\AppNotification;

/**
 * The other half of `PaymentFailedNotification`. Only sent when the workspace
 * was actually told about a failure, so a first-time successful charge stays
 * silent: see `Subscription::clearDunning()`.
 */
class PaymentRecoveredNotification extends AppNotification
{
    public function __construct(Workspace $workspace)
    {
        parent::__construct(
            workspace: $workspace,
            type: NotificationType::PaymentRecovered,
            title: "Payment for {$workspace->name} went through",
            body: 'Your payment method was charged successfully and full access to your plan has been restored.',
            data: [
                'workspace_id' => $workspace->id,
            ],
        );
    }
}
