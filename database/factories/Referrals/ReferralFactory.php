<?php

namespace Database\Factories\Referrals;

use App\Enums\Referrals\ReferralStatus;
use App\Models\Referrals\Referral;
use App\Models\Referrals\ReferralCode;
use App\Models\Referrals\ReferralProgram;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Referral>
 */
class ReferralFactory extends Factory
{
    protected $model = Referral::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'program_id' => ReferralProgram::factory(),
            'referral_code_id' => ReferralCode::factory(),
            'referrer_user_id' => fn (array $attributes) => ReferralCode::find($attributes['referral_code_id'])->user_id,
            'referred_user_id' => User::factory(),
            'status' => ReferralStatus::Pending,
        ];
    }

    public function withStatus(ReferralStatus $status): static
    {
        return $this->state(fn (): array => ['status' => $status]);
    }
}
