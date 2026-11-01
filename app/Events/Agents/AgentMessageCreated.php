<?php

namespace App\Events\Agents;

use App\Broadcasting\BroadcastsReliably;
use App\Broadcasting\Channels;
use App\Models\Agents\AgentMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A message was appended to an agent conversation: the user's own turn, the
 * assistant's reply, or a tool result. Lets a second tab (or a teammate
 * watching a shared session) follow along without polling; the token-by-token
 * view of the *same* reply is `AgentTurnDelta`, streamed by `AgentTurnBroadcaster`.
 *
 * Only says that a message arrived, not what it says: Reverb and Pusher
 * reject any event over 10 KB, which a long reply or its tool calls pass
 * easily, and a rejected event is simply lost. A listener fetches the
 * message itself.
 */
class AgentMessageCreated implements ShouldBroadcast
{
    use BroadcastsReliably, Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly AgentMessage $message) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel(Channels::agentSession($this->message->session))];
    }

    public function broadcastAs(): string
    {
        return 'agent.message';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'agent_session_id' => $this->message->agent_session_id,
            'role' => $this->message->role->value,
            'created_at' => $this->message->created_at?->toIso8601String(),
        ];
    }
}
