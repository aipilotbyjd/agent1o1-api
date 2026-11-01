<?php

namespace Database\Factories\Assistant;

use App\Models\Assistant\Assistant;
use App\Models\Assistant\AssistantSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssistantSession>
 */
class AssistantSessionFactory extends Factory
{
    protected $model = AssistantSession::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assistant_id' => Assistant::factory(),
            'title' => fake()->sentence(4),
            'last_activity_at' => now(),
        ];
    }

    public function incognito(): static
    {
        return $this->state(fn (): array => [
            'incognito' => true,
            'expires_at' => now()->addDay(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'incognito' => true,
            'expires_at' => now()->subMinute(),
        ]);
    }
}
