<?php

namespace App\Notifications\Billing;

use App\Enums\Notifications\NotificationType;
use App\Models\Workspaces\Workspace;
use App\Notifications\AppNotification;

/**
 * Sent only for an ordinary renewal charge (Stripe's `billing_reason:
 * subscription_cycle`), never for the first charge on checkout, and never
 * for a recovered dunning cycle, which already gets
 * `PaymentRecoveredNotification`. `DEFAULT_EMAIL` is `false` on
 * `NotificationType`, so this stays in-app-only unless a workspace opts in.
 */
class SubscriptionRenewedNotification extends AppNotification
{
    public function __construct(Workspace $workspace, ?string $invoiceId = null)
    {
        parent::__construct(
            workspace: $workspace,
            type: NotificationType::SubscriptionRenewed,
            title: "Your subscription for {$workspace->name} renewed",
            body: 'Your subscription renewed successfully and your plan continues uninterrupted.',
            data: [
                'workspace_id' => $workspace->id,
                'invoice_id' => $invoiceId,
            ],
        );
    }

    /**
     * Stripe retries its webhooks: each renewal invoice is reported once.
     */
    public function idempotencyKey(): ?string
    {
        return isset($this->data['invoice_id']) ? 'subscription-renewed:'.$this->data['invoice_id'] : null;
    }
}
