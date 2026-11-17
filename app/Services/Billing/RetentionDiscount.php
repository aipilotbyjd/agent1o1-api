<?php

namespace App\Services\Billing;

use App\Models\Billing\Subscription;
use Laravel\Cashier\Cashier;
use Stripe\Exception\InvalidRequestException;

/**
 * Applies the cancellation flow's discount to a live Stripe subscription.
 *
 * The coupon has no promotion code, so it can never be typed in at checkout:
 * it exists only to be attached here. Its id is derived from its terms, so a
 * change to the configured percent or length makes a new coupon and leaves
 * customers already holding the old one untouched.
 */
class RetentionDiscount
{
    public function apply(Subscription $subscription, int $percent, int $months): void
    {
        $couponId = $this->ensureCoupon($percent, $months);

        // Stripe replaces the whole `discounts` list on update, so whatever
        // the customer already holds (a launch coupon, say) is sent back
        // alongside the new one rather than being swapped out for it.
        $discounts = [];

        foreach ($subscription->asStripeSubscription()->discounts ?? [] as $discount) {
            $discounts[] = ['discount' => is_string($discount) ? $discount : $discount->id];
        }

        $discounts[] = ['coupon' => $couponId];

        $subscription->updateStripeSubscription(['discounts' => $discounts]);
    }

    private function ensureCoupon(int $percent, int $months): string
    {
        $couponId = "retention-{$percent}pct-{$months}mo";

        try {
            Cashier::stripe()->coupons->retrieve($couponId);

            return $couponId;
        } catch (InvalidRequestException) {
            // Not created yet: fall through and create it.
        }

        try {
            Cashier::stripe()->coupons->create([
                'id' => $couponId,
                'name' => "Stay with us: {$percent}% off for {$months} months",
                'percent_off' => $percent,
                'duration' => 'repeating',
                'duration_in_months' => $months,
            ]);
        } catch (InvalidRequestException $exception) {
            // Another request created it between the two calls.
            if ($exception->getStripeCode() !== 'resource_already_exists') {
                throw $exception;
            }
        }

        return $couponId;
    }
}
