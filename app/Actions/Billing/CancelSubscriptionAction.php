<?php

namespace App\Actions\Billing;

use App\Enums\Billing\CancellationOutcome;
use App\Enums\Billing\CancellationReason;
use App\Models\Billing\Subscription;
use App\Models\Billing\SubscriptionCancellation;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Billing\CancellationOffers;

class CancelSubscriptionAction
{
    public function __construct(private readonly CancellationOffers $offers) {}

    /**
     * Schedules the subscription to end with its paid period and records why.
     * The reason is optional so that cancelling never depends on answering a
     * question: a request without one is stored with a null reason.
     */
    public function execute(
        Workspace $workspace,
        ?User $user = null,
        ?CancellationReason $reason = null,
        ?string $comment = null,
    ): Subscription {
        $subscription = $workspace->subscription('default');

        abort_if($subscription === null, 404, 'Workspace has no active subscription to cancel.');

        // Read before cancelling: once the subscription is on its grace
        // period nothing is on offer, which would hide what was shown.
        $offerShown = $this->offers->offerFor($workspace, $reason);

        $subscription->cancel();

        SubscriptionCancellation::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user?->id,
            'plan_id' => $subscription->plan_id,
            'interval' => $this->offers->intervalOf($subscription),
            'reason' => $reason,
            'comment' => $comment,
            'offer' => $offerShown['type'] ?? null,
            'outcome' => CancellationOutcome::Canceled,
            'months_subscribed' => (int) $subscription->created_at?->diffInMonths(now()),
        ]);

        return $subscription;
    }
}
