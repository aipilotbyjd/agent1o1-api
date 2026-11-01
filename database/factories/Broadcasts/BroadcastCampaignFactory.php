<?php

namespace Database\Factories\Broadcasts;

use App\Enums\Broadcasts\BroadcastCategory;
use App\Enums\Broadcasts\BroadcastStatus;
use App\Models\Broadcasts\BroadcastCampaign;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BroadcastCampaign>
 */
class BroadcastCampaignFactory extends Factory
{
    protected $model = BroadcastCampaign::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'category' => BroadcastCategory::Marketing,
            'subject' => fake()->sentence(5),
            'body_markdown' => "Hi {{ first_name }},\n\n".fake()->paragraph(),
            'audience' => [],
            'status' => BroadcastStatus::Draft,
        ];
    }

    public function service(): static
    {
        return $this->state(fn () => ['category' => BroadcastCategory::Service]);
    }

    public function status(BroadcastStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
