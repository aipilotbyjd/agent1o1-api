<?php

namespace Database\Factories;

use App\Models\Credential;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Credential>
 */
class CredentialFactory extends Factory
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
            'name' => fake()->words(2, true),
            'type' => 'api_key',
            'data' => encrypt(json_encode(['type' => 'api_key', 'api_key' => fake()->sha256()])),
        ];
    }
}
