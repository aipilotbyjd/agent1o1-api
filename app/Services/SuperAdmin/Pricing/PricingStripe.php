<?php

namespace App\Services\SuperAdmin\Pricing;

use App\Enums\Billing\BillingInterval;
use Laravel\Cashier\Cashier;
use Stripe\Exception\InvalidRequestException;

/**
 * The one place the pricing manager talks to Stripe, kept apart from the
 * services that decide *what* to change so tests can swap it for a fake.
 *
 * Every create carries an idempotency key, so a retried admin request (or a
 * job re-run after a half-finished change) cannot mint a second price.
 */
class PricingStripe
{
    public function createProduct(string $name, string $planSlug, string $idempotencyKey): string
    {
        return Cashier::stripe()->products->create(
            ['name' => $name, 'metadata' => ['plan_slug' => $planSlug]],
            ['idempotency_key' => $idempotencyKey],
        )->id;
    }

    public function renameProduct(string $productId, string $name): void
    {
        Cashier::stripe()->products->update($productId, ['name' => $name]);
    }

    /**
     * Recurring prices follow the interval; lifetime is a one-time price.
     */
    public function createPrice(string $productId, BillingInterval $interval, int $amountCents, string $idempotencyKey): string
    {
        $params = ['product' => $productId, 'currency' => 'usd', 'unit_amount' => $amountCents];

        $recurring = match ($interval) {
            BillingInterval::Monthly => ['interval' => 'month', 'interval_count' => 1],
            BillingInterval::Quarterly => ['interval' => 'month', 'interval_count' => 3],
            BillingInterval::Yearly => ['interval' => 'year', 'interval_count' => 1],
            BillingInterval::Lifetime => null,
        };

        if ($recurring !== null) {
            $params['recurring'] = $recurring;
        }

        return Cashier::stripe()->prices->create($params, ['idempotency_key' => $idempotencyKey])->id;
    }

    /**
     * Stops a price being used for new purchases. Existing subscriptions on
     * it are untouched: that is Stripe's own behaviour for an inactive price.
     */
    public function archivePrice(string $priceId): void
    {
        Cashier::stripe()->prices->update($priceId, ['active' => false]);
    }

    /**
     * @return array{exists: bool, active: bool, amount_cents: int|null}
     */
    public function inspectPrice(string $priceId): array
    {
        try {
            $price = Cashier::stripe()->prices->retrieve($priceId);
        } catch (InvalidRequestException) {
            return ['exists' => false, 'active' => false, 'amount_cents' => null];
        }

        return ['exists' => true, 'active' => (bool) $price->active, 'amount_cents' => $price->unit_amount === null ? null : (int) $price->unit_amount];
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function createCoupon(array $params, string $idempotencyKey): string
    {
        return Cashier::stripe()->coupons->create($params, ['idempotency_key' => $idempotencyKey])->id;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function createPromotionCode(array $params, string $idempotencyKey): string
    {
        return Cashier::stripe()->promotionCodes->create($params, ['idempotency_key' => $idempotencyKey])->id;
    }

    public function setPromotionCodeActive(string $promotionCodeId, bool $active): void
    {
        Cashier::stripe()->promotionCodes->update($promotionCodeId, ['active' => $active]);
    }

    public function timesRedeemed(string $promotionCodeId): int
    {
        return (int) Cashier::stripe()->promotionCodes->retrieve($promotionCodeId)->times_redeemed;
    }
}
