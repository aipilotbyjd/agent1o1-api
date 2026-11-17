<?php

namespace App\Actions\Billing;

use App\Enums\Billing\CancellationOffer;
use App\Enums\Billing\CancellationOutcome;
use App\Enums\Billing\CancellationReason;
use App\Enums\Notifications\AlertSeverity;
use App\Models\Billing\SubscriptionCancellation;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Billing\CancellationOffers;
use App\Services\Billing\RetentionDiscount;
use App\Services\Notifications\AdminAlerts;
use Illuminate\Support\Facades\Cache;

class AcceptCancellationOfferAction
{
    public function __construct(
        private readonly CancellationOffers $offers,
        private readonly RetentionDiscount $discount,
        private readonly AdminAlerts $alerts,
    ) {}

    /**
     * The customer chose to stay: takes the offer the flow showed for their
     * reason. The offer is worked out again here rather than trusted from the
     * request, so a discount can only be taken by a workspace that is
     * actually eligible for it, at the percent the server offers.
     *
     * Serialised per workspace so a double click cannot stack the discount.
     */
    public function execute(
        Workspace $workspace,
        User $user,
        CancellationOffer $offer,
        CancellationReason $reason,
        ?string $comment = null,
    ): SubscriptionCancellation {
        return Cache::lock("billing:cancellation-offer:{$workspace->id}", 30)->block(10, function () use ($workspace, $user, $offer, $reason, $comment): SubscriptionCancellation {
            $available = $this->offers->offerFor($workspace, $reason);

            abort_unless(
                $offer->isAcceptable() && $available !== null && $available['type'] === $offer,
                422,
                'That offer is not available for this subscription.',
            );

            $subscription = $workspace->subscription('default');

            if ($offer === CancellationOffer::Discount) {
                $this->discount->apply($subscription, (int) $available['percent_off'], (int) $available['months']);
            }

            $record = SubscriptionCancellation::query()->create([
                'workspace_id' => $workspace->id,
                'user_id' => $user->id,
                'plan_id' => $subscription->plan_id,
                'interval' => $this->offers->intervalOf($subscription),
                'reason' => $reason,
                'comment' => $comment,
                'offer' => $offer,
                'outcome' => $offer === CancellationOffer::Discount ? CancellationOutcome::DiscountAccepted : CancellationOutcome::HelpRequested,
                'discount_percent' => $available['percent_off'],
                'discount_months' => $available['months'],
                'months_subscribed' => (int) $subscription->created_at?->diffInMonths(now()),
            ]);

            if ($offer === CancellationOffer::Help) {
                $this->alerts->raise(
                    key: 'billing.cancellation_help_requested',
                    title: "{$workspace->name} asked for help instead of cancelling",
                    body: $comment,
                    context: [
                        'workspace_id' => $workspace->id,
                        'workspace' => $workspace->name,
                        'requested_by' => $user->email,
                        'plan' => $subscription->plan?->slug,
                    ],
                    severity: AlertSeverity::Warning,
                    throttleKey: "billing.cancellation_help_requested:{$workspace->id}",
                );
            }

            return $record;
        });
    }
}
