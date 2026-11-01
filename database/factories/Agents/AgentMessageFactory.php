<?php

namespace Database\Factories\Agents;

use App\Enums\Agents\AgentMessageRole;
use App\Models\Agents\AgentMessage;
use App\Models\Agents\AgentSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentMessage>
 */
class AgentMessageFactory extends Factory
{
    protected $model = AgentMessage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agent_session_id' => AgentSession::factory(),
            'role' => AgentMessageRole::User,
            'content' => fake()->sentence(),
        ];
    }

    public function forSession(AgentSession $session): static
    {
        return $this->state(fn (): array => ['agent_session_id' => $session->id]);
    }

    public function assistant(): static
    {
        return $this->state(fn (): array => ['role' => AgentMessageRole::Assistant]);
    }
}
