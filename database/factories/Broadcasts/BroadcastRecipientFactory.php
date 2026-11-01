<?php

namespace Database\Factories\Broadcasts;

use App\Models\Broadcasts\BroadcastCampaign;
use App\Models\Broadcasts\BroadcastRecipient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BroadcastRecipient>
 */
class BroadcastRecipientFactory extends Factory
{
    protected $model = BroadcastRecipient::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => BroadcastCampaign::factory(),
            'user_id' => User::factory(),
            'email' => fake()->unique()->safeEmail(),
            'status' => 'pending',
        ];
    }
}
