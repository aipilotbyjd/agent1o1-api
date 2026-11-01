<?php

namespace Database\Factories\Billing;

use App\Enums\Billing\CouponDuration;
use App\Models\Billing\PricingCoupon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PricingCoupon>
 */
class PricingCouponFactory extends Factory
{
    protected $model = PricingCoupon::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'code' => strtoupper(fake()->unique()->bothify('????##')),
            'percent_off' => 20,
            'amount_off_cents' => null,
            'currency' => 'usd',
            'duration' => CouponDuration::Once,
            'is_active' => true,
            'stripe_coupon_id' => 'coupon_'.fake()->unique()->uuid(),
            'stripe_promotion_code_id' => 'promo_'.fake()->unique()->uuid(),
        ];
    }
}
