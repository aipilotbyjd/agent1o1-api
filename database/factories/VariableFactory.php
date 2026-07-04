<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Variable;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Variable>
 */
class VariableFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'created_by' => User::factory(),
            'key' => fake()->unique()->word().'_'.fake()->randomNumber(3),
            'value' => fake()->word(),
            'is_secret' => false,
        ];
    }

    public function secret(): static
    {
        return $this->state(fn () => [
            'value' => encrypt(fake()->sha256()),
            'is_secret' => true,
        ]);
    }
}
