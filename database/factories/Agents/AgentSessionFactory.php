<?php

namespace Database\Factories\Agents;

use App\Models\Agents\Agent;
use App\Models\Agents\AgentSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentSession>
 */
class AgentSessionFactory extends Factory
{
    protected $model = AgentSession::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $agent = Agent::factory()->create();

        return [
            'workspace_id' => $agent->workspace_id,
            'agent_id' => $agent->id,
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
