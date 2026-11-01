<?php

namespace Database\Factories\Agents;

use App\Enums\Agents\ActionEffect;
use App\Enums\Agents\ActionToolKind;
use App\Enums\Agents\ActionVerdict;
use App\Enums\Agents\AgentActionStatus;
use App\Models\Agents\AgentAction;
use App\Models\Agents\AgentSession;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AgentAction>
 */
class AgentActionFactory extends Factory
{
    protected $model = AgentAction::class;

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
            'tool_call_id' => 'call-'.Str::random(8),
            'tool_name' => 'gmail_send_email',
            'tool_kind' => ActionToolKind::Node,
            'effect' => ActionEffect::External,
            'arguments' => ['to' => 'someone@example.com', 'subject' => 'Hello', 'body' => 'Hi there'],
            'outcome' => ActionVerdict::Ask,
            'status' => AgentActionStatus::Pending,
            'requested_at' => now(),
            'expires_at' => now()->addDay(),
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

    public function withStatus(AgentActionStatus $status): static
    {
        return $this->state(fn (): array => ['status' => $status]);
    }
}
