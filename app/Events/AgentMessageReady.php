<?php

namespace App\Events;

use App\Models\Agent;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Terminal event for an agent conversation turn — fired once ProcessAgentMessageJob
 * finishes streaming (success or failure), mirroring BuilderMessageReady.
 */
class AgentMessageReady implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $channelKey,
        public readonly ?string $conversationId,
        public readonly string $text,
        public readonly Agent $agent,
        public readonly bool $error = false,
        public readonly ?string $errorMessage = null,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("agent.stream.{$this->channelKey}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'agent.message.ready';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'response' => $this->text,
            'agent_id' => $this->agent->id,
            'error' => $this->error,
            'error_message' => $this->errorMessage,
        ];
    }
}
