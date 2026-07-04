<?php

namespace Database\Factories;

use App\Enums\CreditTransactionType;
use App\Models\CreditTransaction;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditTransaction>
 */
class CreditTransactionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'usage_period_id' => null,
            'type' => fake()->randomElement(CreditTransactionType::cases())->value,
            'credits' => fake()->numberBetween(1, 500),
            'description' => fake()->sentence(),
            'created_at' => fake()->dateTimeBetween('-30 days', 'now'),
        ];
    }

    public function usage(): static
    {
        return $this->state(fn () => [
            'type' => CreditTransactionType::Usage,
            'credits' => fake()->numberBetween(1, 50),
            'description' => 'Workflow execution credit usage',
        ]);
    }

    public function grant(): static
    {
        return $this->state(fn () => [
            'type' => CreditTransactionType::Grant,
            'credits' => fake()->numberBetween(100, 1000),
            'description' => 'Credits granted',
        ]);
    }
}
