<?php

namespace App\Actions\Billing;

use App\Enums\Billing\BillingInterval;
use App\Models\Billing\Plan;
use App\Models\Billing\PricingCoupon;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Billing\Policies\TrialCreditPolicy;
use App\Services\Referrals\ReferralTrialBonus;
use Laravel\Cashier\Checkout;

class CheckoutSubscriptionAction
{
    private const SUBSCRIPTION_TYPE = 'default';

    public function __construct(
        private readonly ReferralTrialBonus $referralTrialBonus,
        private readonly TrialCreditPolicy $trials,
    ) {}

    /**
     * Returns a Stripe Checkout URL for a workspace with no subscription yet,
     * or swaps the existing subscription's price in place and returns null.
     *
     * Recurring intervals only. `Lifetime` is a one-off `mode=payment`
     * purchase with no subscription behind it, so it goes through
     * `CheckoutLifetimePlanAction`: guarded here rather than left to fail
     * deeper in Cashier with an unrecurring price.
     */
    public function execute(Workspace $workspace, Plan $plan, BillingInterval $interval, ?User $buyer = null): ?string
    {
        abort_if(
            ! $interval->isRecurring(),
            422,
            "Interval [{$interval->value}] is not a recurring subscription.",
        );

        // Covers an inactive plan, an interval withdrawn estate-wide, one this
        // plan doesn't sell, and a missing Stripe price. Guarding here rather
        // than only in the controller means a swap can't move an existing
        // subscription onto a withdrawn interval either.
        $unavailable = $plan->unavailableReasonFor($interval);

        abort_if($unavailable !== null, 422, $unavailable);

        $priceId = $plan->stripePriceId($interval);

        $subscription = $workspace->subscription(self::SUBSCRIPTION_TYPE);

        if ($subscription !== null) {
            $subscription->swap($priceId);
            $subscription->update(['plan_id' => $plan->id]);

            return null;
        }

        $builder = $workspace->newSubscription(self::SUBSCRIPTION_TYPE, $priceId);

        // Cashier's trialDays() bakes the trial into an absolute `trial_end`
        // timestamp computed when the Checkout Session is created. Stripe's
        // Checkout page then displays "days free" as time-remaining-until-
        // that-timestamp at render time, which is always a hair under the
        // configured length, so a 14-day trial reads as "13 days free".
        // Passing `trial_period_days` instead is relative to when the
        // customer actually completes checkout, so the displayed and actual
        // trial length always match.
        $sessionOptions = [
            'success_url' => $this->successUrl($workspace),
            'cancel_url' => $this->cancelUrl($workspace),
        ];

        // Show Stripe's promo-code field only once an admin has created a code.
        if (PricingCoupon::query()->where('is_active', true)->exists()) {
            $sessionOptions['allow_promotion_codes'] = true;
        }

        // Referral `trial_extension` rewards lengthen the plan's own trial,
        // or grant one on a plan that has none.
        // One trial per workspace, person and mailbox: a repeat checkout pays
        // from day one. The card is checked once Stripe reports it.
        $trialDays = $this->trials->trialDeniedReason($workspace, $buyer) === null
            ? $plan->trial_days + $this->referralTrialBonus->daysFor($workspace)
            : 0;

        if ($trialDays > 0) {
            $sessionOptions['subscription_data'] = ['trial_period_days' => $trialDays];
        }

        /** @var Checkout $checkout */
        $checkout = $builder->checkout($sessionOptions);

        return $checkout->asStripeCheckoutSession()->url;
    }

    private function successUrl(Workspace $workspace): string
    {
        return rtrim((string) config('app.frontend_url'), '/')."/workspaces/{$workspace->slug}/billing?checkout=success";
    }

    private function cancelUrl(Workspace $workspace): string
    {
        return rtrim((string) config('app.frontend_url'), '/')."/workspaces/{$workspace->slug}/billing?checkout=cancel";
    }
}
