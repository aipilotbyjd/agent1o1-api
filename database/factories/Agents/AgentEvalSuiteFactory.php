<?php

namespace Database\Factories\Agents;

use App\Models\Agents\Agent;
use App\Models\Agents\AgentEvalSuite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentEvalSuite>
 */
class AgentEvalSuiteFactory extends Factory
{
    protected $model = AgentEvalSuite::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $agent = Agent::factory()->create();

        return [
            'workspace_id' => $agent->workspace_id,
            'agent_id' => $agent->id,
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
        ];
    }

    public function forAgent(Agent $agent): static
    {
        return $this->state(fn (): array => [
            'workspace_id' => $agent->workspace_id,
            'agent_id' => $agent->id,
        ]);
    }
}
