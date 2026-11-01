<?php

namespace App\Http\Resources\Api\SuperAdmin\V1\Pricing;

use App\Models\Billing\PricingCoupon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PricingCoupon
 */
class PricingCouponResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'percent_off' => $this->percent_off,
            'amount_off_cents' => $this->amount_off_cents,
            'duration' => $this->duration->value,
            'duration_in_months' => $this->duration_in_months,
            'max_redemptions' => $this->max_redemptions,
            'times_redeemed' => $this->times_redeemed,
            'redeem_by' => $this->redeem_by,
            'plan_ids' => $this->plan_ids ?? [],
            'is_active' => $this->is_active,
            'stripe_coupon_id' => $this->stripe_coupon_id,
            'stripe_promotion_code_id' => $this->stripe_promotion_code_id,
            'created_at' => $this->created_at,
        ];
    }
}
