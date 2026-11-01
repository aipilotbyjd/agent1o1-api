<?php

namespace App\Events\Agents;

use App\Broadcasting\BroadcastsReliably;
use App\Broadcasting\Channels;
use App\Models\Agents\AgentSession;
use App\Models\Runs\Run;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Actions in a conversation were decided, expired, cancelled or settled,
 * so an approval card open in another tab (or another person's inbox) can
 * refresh rather than offer a decision that's already been made.
 */
class AgentActionsChanged implements ShouldBroadcast
{
    use BroadcastsReliably, Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly AgentSession $session,
        public readonly ?Run $run = null,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel(Channels::agentSession($this->session))];
    }

    public function broadcastAs(): string
    {
        return 'agent.actions.changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'agent_session_id' => $this->session->id,
            'run_id' => $this->run?->id,
        ];
    }
}
