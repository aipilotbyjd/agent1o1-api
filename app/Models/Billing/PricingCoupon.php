<?php

namespace App\Models\Billing;

use App\Enums\Billing\CouponDuration;
use Database\Factories\Billing\PricingCouponFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A discount code, mirrored to a Stripe coupon plus promotion code. Stripe
 * coupons cannot be edited, so only `is_active` (the promotion code's
 * switch) ever changes after creation.
 */
#[Fillable([
    'name', 'code', 'percent_off', 'amount_off_cents', 'currency', 'duration', 'duration_in_months',
    'max_redemptions', 'times_redeemed', 'redeem_by', 'plan_ids', 'is_active',
    'stripe_coupon_id', 'stripe_promotion_code_id', 'created_by',
])]
class PricingCoupon extends Model
{
    /** @use HasFactory<PricingCouponFactory> */
    use HasFactory, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'duration' => CouponDuration::class,
            'percent_off' => 'integer',
            'amount_off_cents' => 'integer',
            'duration_in_months' => 'integer',
            'max_redemptions' => 'integer',
            'times_redeemed' => 'integer',
            'redeem_by' => 'datetime',
            'plan_ids' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
