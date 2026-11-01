<?php

namespace Database\Factories\Assistant;

use App\Enums\Assistant\AssistantFollowUpStatus;
use App\Models\Assistant\Assistant;
use App\Models\Assistant\AssistantFollowUp;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AssistantFollowUp>
 */
class AssistantFollowUpFactory extends Factory
{
    protected $model = AssistantFollowUp::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assistant_id' => Assistant::factory(),
            'thread_id' => 'thread_'.Str::random(10),
            'subject' => fake()->sentence(4),
            'status' => AssistantFollowUpStatus::Pending,
            'remind_at' => now()->addDays(3),
        ];
    }

    public function due(): static
    {
        return $this->state(fn (): array => ['remind_at' => now()->subMinute(), 'created_at' => now()->subDays(3)]);
    }
}
