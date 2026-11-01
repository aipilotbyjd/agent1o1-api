<?php

namespace Database\Factories\Agents;

use App\Models\Agents\Agent;
use App\Models\Agents\ReflectionRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReflectionRun>
 */
class ReflectionRunFactory extends Factory
{
    protected $model = ReflectionRun::class;

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
