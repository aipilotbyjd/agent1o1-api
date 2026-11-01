<?php

namespace App\Events\Assistant;

use App\Broadcasting\Channels;
use App\Models\Assistant\AssistantTurn;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A chunk of the reply as the model writes it. Batched by `TurnRunner` so a
 * long reply is a handful of messages, not one per token.
 */
class AssistantTurnDelta implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(public AssistantTurn $turn, public string $text) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel(Channels::assistantSession($this->turn->session));
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
        return ['turn_id' => $this->turn->id, 'text' => $this->text];
    }
}
