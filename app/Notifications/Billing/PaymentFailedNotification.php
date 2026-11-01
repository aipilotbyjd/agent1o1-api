<?php

namespace App\Notifications\Billing;

use App\Enums\Notifications\NotificationEvent;
use App\Models\Workspaces\Workspace;
use App\Notifications\Workspace\WorkspaceEventNotification;
use Carbon\CarbonInterface;

/**
 * Sent on every failed collection attempt, escalating with Stripe's own
 * `attempt_count` rather than a timer of ours.
 *
 * The wording is blunt about access because this app grants no grace period:
 * a `past_due` subscription stops entitling immediately (see
 * `Workspace::activeSubscription()`), since every workflow run costs real
 * model spend. Softening that to "you may lose access" would be a lie.
 */
class PaymentFailedNotification extends WorkspaceEventNotification
{
    public function __construct(
        Workspace $workspace,
        int $attempt = 1,
        ?string $invoiceId = null,
        ?CarbonInterface $nextAttemptAt = null,
    ) {
        parent::__construct(
            workspace: $workspace,
            event: NotificationEvent::PaymentFailed,
            title: $attempt > 1
                ? "Payment for {$workspace->name} failed again (attempt {$attempt})"
                : "A payment for {$workspace->name} failed",
            body: self::body($attempt, $nextAttemptAt),
            data: [
                'workspace_id' => $workspace->id,
                'attempt' => $attempt,
                'invoice_id' => $invoiceId,
                'next_attempt_at' => $nextAttemptAt?->toIso8601String(),
            ],
        );
    }

    private static function body(int $attempt, ?CarbonInterface $nextAttemptAt): string
    {
        $opening = $attempt > 1
            ? 'We still could not charge your payment method.'
            : 'Stripe could not charge your payment method.';

        $consequence = ' Paid features are suspended until the payment succeeds: workflow runs and agent turns will be refused in the meantime.';

        $next = $nextAttemptAt !== null
            ? " Stripe will try again on {$nextAttemptAt->toFormattedDateString()}, or you can update your card now to restore access immediately."
            : ' Update your card to restore access.';

        return $opening.$consequence.$next;
    }
}
