<?php

namespace App\Actions\Billing;

use App\Enums\Billing\BillingInterval;
use App\Enums\Billing\PlanGrantSource;
use App\Models\Billing\Plan;
use App\Models\Billing\PlanGrant;
use App\Models\Billing\PricingCoupon;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use Laravel\Cashier\Checkout;

/**
 * Buys a plan outright. A lifetime plan is not a Stripe subscription: it is
 * a one-off `mode=payment` Checkout Session, so no `customer.subscription.*`
 * webhook ever fires for it and nothing lands in `subscriptions`. Entitlement
 * instead comes from the `PlanGrant` this records: `Pending` until
 * `checkout.session.completed` arrives, then flipped `Active` by
 * `ActivatePlanGrantAction`.
 *
 * Prices off the plan's configured one-time Stripe price rather than
 * `checkoutCharge()`'s inline amount (the route credit packs take), so
 * lifetime revenue reports against the same Stripe product as the plan's
 * recurring prices.
 */
class CheckoutLifetimePlanAction
{
    /**
     * @return array{grant: PlanGrant, checkout_url: string}
     */
    public function execute(Workspace $workspace, Plan $plan, User $purchaser): array
    {
        $unavailable = $plan->unavailableReasonFor(BillingInterval::Lifetime);

        abort_if($unavailable !== null, 422, $unavailable);

        $priceId = $plan->stripePriceId(BillingInterval::Lifetime);

        abort_if(
            $workspace->planGrants()->active()->where('plan_id', $plan->id)->exists(),
            422,
            "Workspace already holds a lifetime grant for plan [{$plan->slug}].",
        );

        $grant = $workspace->planGrants()->create([
            'plan_id' => $plan->id,
            'purchased_by' => $purchaser->id,
            'source' => PlanGrantSource::LifetimePurchase,
            'price_cents' => $plan->price_lifetime,
        ]);

        /** @var Checkout $checkout */
        $checkout = $workspace->checkout([$priceId => 1], [
            'mode' => 'payment',
            'allow_promotion_codes' => PricingCoupon::query()->where('is_active', true)->exists(),
            'success_url' => $this->successUrl($workspace),
            'cancel_url' => $this->cancelUrl($workspace),
            'metadata' => [
                'type' => 'plan_grant',
                'plan_grant_id' => $grant->id,
            ],
        ]);

        $stripeSession = $checkout->asStripeCheckoutSession();

        $grant->update(['stripe_checkout_session_id' => $stripeSession->id]);

        return ['grant' => $grant, 'checkout_url' => $stripeSession->url];
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
