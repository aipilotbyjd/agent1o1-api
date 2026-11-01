<?php

namespace Database\Factories\Agents;

use App\Models\Agents\AgentPlan;
use App\Models\Agents\AgentSession;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AgentPlan>
 */
class AgentPlanFactory extends Factory
{
    protected $model = AgentPlan::class;

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
            'title' => 'Email the weekly report',
            'summary' => fake()->sentence(),
            'steps' => [
                [
                    'id' => (string) Str::uuid(),
                    'tool' => 'gmail_send_email',
                    'summary' => 'Send the report to the team',
                    'arguments' => ['to' => 'team@example.com'],
                    'status' => AgentPlan::STEP_PENDING,
                ],
            ],
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
