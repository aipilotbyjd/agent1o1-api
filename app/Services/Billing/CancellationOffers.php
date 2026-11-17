<?php

namespace App\Services\Billing;

use App\Enums\Billing\BillingInterval;
use App\Enums\Billing\CancellationOffer;
use App\Enums\Billing\CancellationOutcome;
use App\Enums\Billing\CancellationReason;
use App\Models\Billing\Plan;
use App\Models\Billing\Subscription;
use App\Models\Billing\SubscriptionCancellation;
use App\Models\Workspaces\Workspace;

/**
 * Decides the single alternative the cancellation flow shows for a reason.
 * At most one offer per reason, and none at all for a subscription that is
 * on trial, failing payment, or already scheduled to end.
 *
 * The discount is deliberately narrow: monthly subscriptions only (a coupon
 * lasting a few months would expire before a quarterly or yearly renewal),
 * and once per workspace per cooldown so cancelling never becomes a way to
 * collect discounts.
 */
class CancellationOffers
{
    /**
     * @return array{type: CancellationOffer, percent_off: int|null, months: int|null}|null
     */
    public function offerFor(Workspace $workspace, ?CancellationReason $reason): ?array
    {
        $subscription = $workspace->subscription('default');

        if ($reason === null || $subscription === null || ! $this->canBeOffered($subscription)) {
            return null;
        }

        return match ($reason) {
            CancellationReason::TooExpensive => $this->discount($workspace, $subscription) ?? $this->downgrade($subscription),
            CancellationReason::NotUsingEnough => $this->downgrade($subscription),
            CancellationReason::TooHard => $this->offer(CancellationOffer::Help),
            default => null,
        };
    }

    /**
     * The interval a subscription renews on, read from which of its plan's
     * Stripe prices it holds. Null when the price is no longer on the plan.
     */
    public function intervalOf(Subscription $subscription): ?BillingInterval
    {
        $plan = $subscription->plan;
        $stripePrice = (string) $subscription->stripe_price;

        if ($plan === null || $stripePrice === '') {
            return null;
        }

        foreach (BillingInterval::cases() as $interval) {
            if ($plan->stripePriceId($interval) === $stripePrice) {
                return $interval;
            }
        }

        return null;
    }

    private function canBeOffered(Subscription $subscription): bool
    {
        return $subscription->active()
            && ! $subscription->onTrial()
            && ! $subscription->onGracePeriod()
            && ! $subscription->inDunning();
    }

    /**
     * @return array{type: CancellationOffer, percent_off: int|null, months: int|null}|null
     */
    private function discount(Workspace $workspace, Subscription $subscription): ?array
    {
        $percent = (int) config('billing.cancellation.discount_percent');
        $months = (int) config('billing.cancellation.discount_months');

        if ($percent < 1 || $percent > 100 || $months < 1) {
            return null;
        }

        if ($this->intervalOf($subscription) !== BillingInterval::Monthly) {
            return null;
        }

        $alreadyGiven = SubscriptionCancellation::query()
            ->where('workspace_id', $workspace->id)
            ->where('outcome', CancellationOutcome::DiscountAccepted)
            ->where('created_at', '>=', now()->subMonths((int) config('billing.cancellation.offer_cooldown_months')))
            ->exists();

        return $alreadyGiven ? null : $this->offer(CancellationOffer::Discount, $percent, $months);
    }

    /**
     * Offered only when a cheaper paid plan is on sale for the same interval:
     * moving to the free plan is what cancelling already does.
     *
     * @return array{type: CancellationOffer, percent_off: int|null, months: int|null}|null
     */
    private function downgrade(Subscription $subscription): ?array
    {
        $interval = $this->intervalOf($subscription);

        if ($interval === null || $subscription->plan === null) {
            return null;
        }

        $currentPrice = $subscription->plan->priceFor($interval);

        $hasCheaperPlan = Plan::query()
            ->where('is_active', true)
            ->where('is_public', true)
            ->whereNull('archived_at')
            ->whereKeyNot($subscription->plan->id)
            ->get()
            ->contains(fn (Plan $plan): bool => $plan->isSoldOn($interval)
                && $plan->priceFor($interval) > 0
                && $plan->priceFor($interval) < $currentPrice);

        return $hasCheaperPlan ? $this->offer(CancellationOffer::Downgrade) : null;
    }

    /**
     * @return array{type: CancellationOffer, percent_off: int|null, months: int|null}
     */
    private function offer(CancellationOffer $type, ?int $percent = null, ?int $months = null): array
    {
        return ['type' => $type, 'percent_off' => $percent, 'months' => $months];
    }
}
