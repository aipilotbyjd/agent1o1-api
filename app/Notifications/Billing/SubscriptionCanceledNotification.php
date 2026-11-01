<?php

namespace App\Notifications\Billing;

use App\Enums\Notifications\NotificationEvent;
use App\Models\Workspaces\Workspace;
use App\Notifications\Workspace\WorkspaceEventNotification;

/**
 * The end of the dunning road: Stripe has exhausted its retry schedule and
 * dropped the subscription, or it was canceled outright. Distinguishes the
 * two so a customer who chose to cancel isn't told their card failed.
 */
class SubscriptionCanceledNotification extends WorkspaceEventNotification
{
    public function __construct(Workspace $workspace, bool $afterFailedPayments = false)
    {
        parent::__construct(
            workspace: $workspace,
            event: NotificationEvent::SubscriptionCanceled,
            title: "The subscription for {$workspace->name} has ended",
            body: $afterFailedPayments
                ? 'Stripe was unable to collect payment after several attempts, so the subscription has been canceled. The workspace has moved to the free plan: subscribe again at any time to restore your previous limits.'
                : 'The subscription has been canceled and the workspace has moved to the free plan.',
            data: [
                'workspace_id' => $workspace->id,
                'after_failed_payments' => $afterFailedPayments,
            ],
        );
    }
}
