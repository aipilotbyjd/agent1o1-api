<?php

namespace Database\Factories\Referrals;

use App\Enums\Referrals\ReferralRecipient;
use App\Enums\Referrals\ReferralRewardStatus;
use App\Enums\Referrals\ReferralRewardType;
use App\Enums\Referrals\ReferralTrigger;
use App\Models\Referrals\ReferralReward;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ReferralReward>
 */
class ReferralRewardFactory extends Factory
{
    protected $model = ReferralReward::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'recipient_user_id' => User::factory(),
            'recipient_role' => ReferralRecipient::Referrer,
            'trigger' => ReferralTrigger::Manual,
            'reward_type' => ReferralRewardType::Credits,
            'credits' => 100,
            'status' => ReferralRewardStatus::Pending,
            'idempotency_key' => 'test:'.Str::uuid(),
        ];
    }
}
