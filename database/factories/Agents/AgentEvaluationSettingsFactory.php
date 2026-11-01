<?php

namespace Database\Factories\Agents;

use App\Models\Agents\Agent;
use App\Models\Agents\AgentEvaluationSettings;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentEvaluationSettings>
 */
class AgentEvaluationSettingsFactory extends Factory
{
    protected $model = AgentEvaluationSettings::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agent_id' => Agent::factory(),
        ];
    }

    public function forAgent(Agent $agent): static
    {
        return $this->state(fn (): array => ['agent_id' => $agent->id]);
    }

    public function enabled(): static
    {
        return $this->state(fn (): array => ['is_enabled' => true]);
    }

    /**
     * @param  array<int, array{name: string, prompt: string, type?: string, priority?: string}>  $criteria
     */
    public function withCriteria(array $criteria): static
    {
        return $this->state(fn (): array => ['criteria' => $criteria]);
    }
}
