<?php

namespace App\Events\Agents;

use App\Broadcasting\Channels;
use App\Models\Agents\AgentSession;
use App\Models\Runs\Run;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A chunk of the model's reasoning, for the chat to show live while the
 * model thinks. Batched by `AgentTurnBroadcaster` like `AgentTurnDelta`.
 *
 * This is the live view: the finished reasoning is kept on the reply
 * (`AgentMessage::$reasoning`) and comes back with the stored message, so a
 * reconnecting client reads it from there. Channels such as email or Slack
 * never see it.
 */
class AgentTurnReasoning implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(
        public readonly AgentSession $session,
        public readonly Run $run,
        public readonly string $text,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel(Channels::agentSession($this->session));
    }

    public function broadcastAs(): string
    {
        return 'turn.reasoning';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['run_id' => $this->run->id, 'text' => $this->text];
    }
}
