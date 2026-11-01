<?php

namespace App\Services\SuperAdmin\Pricing;

use App\Enums\Billing\CouponDuration;
use App\Models\Billing\Plan;
use App\Models\Billing\PricingCoupon;
use App\Models\SuperAdmin\SuperAdmin;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Stripe\Exception\ApiErrorException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Discount codes, mirrored into Stripe as a coupon plus a promotion code
 * (the thing a customer actually types at Checkout). Stripe coupons can't be
 * edited, so after creation the only change is switching the code on or off.
 *
 * Stripe is written first and the local row after, so a Stripe rejection
 * (a code already taken, say) leaves nothing behind; if the local write then
 * fails the promotion code is switched off again.
 */
class SuperAdminCouponService
{
    public function __construct(
        private readonly PricingStripe $stripe,
        private readonly PricingChangeLogger $log,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(SuperAdmin $actor, array $data, string $reason): PricingCoupon
    {
        $planIds = array_values((array) ($data['plan_ids'] ?? []));
        $products = $this->productsFor($planIds);
        $code = strtoupper((string) $data['code']);

        $promotionCodeId = null;

        try {
            $couponId = $this->stripe->createCoupon($this->couponParams($data, $products), "coupon:{$code}");

            $promotionCodeId = $this->stripe->createPromotionCode(array_filter([
                'coupon' => $couponId,
                'code' => $code,
                'max_redemptions' => $data['max_redemptions'] ?? null,
                'expires_at' => isset($data['redeem_by']) ? Carbon::parse($data['redeem_by'])->getTimestamp() : null,
            ], fn (mixed $value): bool => $value !== null), "promo:{$code}");

            return DB::transaction(function () use ($actor, $data, $planIds, $code, $couponId, $promotionCodeId, $reason): PricingCoupon {
                $coupon = PricingCoupon::query()->create([
                    'name' => $data['name'],
                    'code' => $code,
                    'percent_off' => $data['percent_off'] ?? null,
                    'amount_off_cents' => $data['amount_off_cents'] ?? null,
                    'duration' => $data['duration'],
                    'duration_in_months' => $data['duration'] === CouponDuration::Repeating->value ? $data['duration_in_months'] : null,
                    'max_redemptions' => $data['max_redemptions'] ?? null,
                    'redeem_by' => $data['redeem_by'] ?? null,
                    'plan_ids' => $planIds === [] ? null : $planIds,
                    'is_active' => true,
                    'stripe_coupon_id' => $couponId,
                    'stripe_promotion_code_id' => $promotionCodeId,
                    'created_by' => $actor->id,
                ]);

                $this->log->record($actor, 'coupon', 'created', $coupon, null, $this->present($coupon), $reason, $coupon->code);

                return $coupon;
            });
        } catch (Throwable $e) {
            if ($promotionCodeId !== null) {
                try {
                    $this->stripe->setPromotionCodeActive($promotionCodeId, false);
                } catch (Throwable $cleanup) {
                    report($cleanup);
                }
            }

            throw $e instanceof ApiErrorException
                ? new HttpException(422, 'Stripe rejected the coupon: '.$e->getMessage(), $e)
                : $e;
        }
    }

    public function setActive(SuperAdmin $actor, PricingCoupon $coupon, bool $active, string $reason): PricingCoupon
    {
        if ($coupon->is_active === $active) {
            return $coupon;
        }

        return DB::transaction(function () use ($actor, $coupon, $active, $reason): PricingCoupon {
            $before = ['is_active' => $coupon->is_active];

            if ($coupon->stripe_promotion_code_id !== null) {
                $this->stripe->setPromotionCodeActive($coupon->stripe_promotion_code_id, $active);
            }

            $coupon->forceFill(['is_active' => $active])->save();

            $this->log->record($actor, 'coupon', $active ? 'activated' : 'deactivated', $coupon, $before, ['is_active' => $active], $reason, $coupon->code);

            return $coupon;
        });
    }

    /**
     * Pulls the redemption count Stripe holds for the code.
     */
    public function refreshRedemptions(PricingCoupon $coupon): PricingCoupon
    {
        if ($coupon->stripe_promotion_code_id !== null) {
            $coupon->forceFill(['times_redeemed' => $this->stripe->timesRedeemed($coupon->stripe_promotion_code_id)])->save();
        }

        return $coupon;
    }

    /**
     * @param  list<string>  $planIds
     * @return list<string>
     */
    private function productsFor(array $planIds): array
    {
        if ($planIds === []) {
            return [];
        }

        $plans = Plan::query()->whereIn('id', $planIds)->get();

        foreach ($plans as $plan) {
            if ($plan->stripe_product_id === null) {
                throw new HttpException(422, "Plan [{$plan->slug}] has no Stripe product yet, so a code cannot be limited to it. Sync it with Stripe first.");
            }
        }

        return $plans->pluck('stripe_product_id')->values()->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $products
     * @return array<string, mixed>
     */
    private function couponParams(array $data, array $products): array
    {
        $params = ['name' => $data['name'], 'duration' => $data['duration']];

        if (isset($data['percent_off'])) {
            $params['percent_off'] = $data['percent_off'];
        } else {
            $params['amount_off'] = $data['amount_off_cents'];
            $params['currency'] = 'usd';
        }

        if ($data['duration'] === CouponDuration::Repeating->value) {
            $params['duration_in_months'] = $data['duration_in_months'];
        }

        if ($products !== []) {
            $params['applies_to'] = ['products' => $products];
        }

        return $params;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(PricingCoupon $coupon): array
    {
        return [
            'code' => $coupon->code,
            'percent_off' => $coupon->percent_off,
            'amount_off_cents' => $coupon->amount_off_cents,
            'duration' => $coupon->duration->value,
            'duration_in_months' => $coupon->duration_in_months,
            'max_redemptions' => $coupon->max_redemptions,
            'redeem_by' => $coupon->redeem_by?->toIso8601String(),
            'plan_ids' => $coupon->plan_ids,
            'is_active' => $coupon->is_active,
        ];
    }
}
