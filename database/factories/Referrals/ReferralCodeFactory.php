<?php

namespace Database\Factories\Referrals;

use App\Models\Referrals\ReferralCode;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ReferralCode>
 */
class ReferralCodeFactory extends Factory
{
    protected $model = ReferralCode::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'code' => Str::lower(Str::random(10)),
            'is_active' => true,
            'rule_multiplier' => 1,
        ];
    }
}
