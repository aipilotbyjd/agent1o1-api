<?php

namespace Database\Factories\Agents;

use App\Models\Agents\Agent;
use App\Models\Agents\AgentMemory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentMemory>
 */
class AgentMemoryFactory extends Factory
{
    protected $model = AgentMemory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agent_id' => Agent::factory(),
            'key' => $this->faker->unique()->word(),
            'value' => $this->faker->sentence(),
            'type' => 'fact',
        ];
    }

    public function forAgent(Agent $agent): static
    {
        return $this->state(fn (): array => ['agent_id' => $agent->id]);
    }
}
