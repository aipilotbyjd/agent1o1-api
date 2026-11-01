<?php

namespace Database\Factories\Agents;

use App\Models\Agents\AgentSession;
use App\Models\Agents\AgentSessionEvaluation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentSessionEvaluation>
 */
class AgentSessionEvaluationFactory extends Factory
{
    protected $model = AgentSessionEvaluation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $session = AgentSession::factory()->create();

        return [
            'workspace_id' => $session->workspace_id,
            'agent_id' => $session->agent_id,
            'agent_session_id' => $session->id,
        ];
    }

    public function forSession(AgentSession $session): static
    {
        return $this->state(fn (): array => [
            'workspace_id' => $session->workspace_id,
            'agent_id' => $session->agent_id,
            'agent_session_id' => $session->id,
        ]);
    }
}
