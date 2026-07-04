<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\AgentTrigger;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentTrigger>
 */
class AgentTriggerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agent_id' => Agent::factory(),
            'workspace_id' => Workspace::factory(),
            'type' => 'webhook',
            'config' => [],
            'initial_message' => fake()->sentence(),
            'is_active' => true,
        ];
    }

    public function webhook(): static
    {
        return $this->state(fn () => ['type' => 'webhook']);
    }

    public function schedule(): static
    {
        return $this->state(fn () => ['type' => 'schedule']);
    }
}
