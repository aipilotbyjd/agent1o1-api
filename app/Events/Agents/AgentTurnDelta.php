<?php

namespace App\Events\Agents;

use App\Broadcasting\Channels;
use App\Models\Agents\AgentSession;
use App\Models\Runs\Run;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A chunk of the reply as the model writes it. Batched by
 * `AgentTurnBroadcaster` so a long reply is a handful of messages, not one
 * per token, and each stays far below Reverb's 10 KB event limit.
 */
class AgentTurnDelta implements ShouldBroadcastNow
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
        return 'turn.delta';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['run_id' => $this->run->id, 'text' => $this->text];
    }
}
