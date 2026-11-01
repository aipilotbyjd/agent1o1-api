<?php

namespace App\Events\Assistant;

use App\Broadcasting\Channels;
use App\Models\Assistant\AssistantTurn;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A turn moved: started, paused for approvals, finished, failed or was
 * cancelled. The frontend refetches the transcript on anything final.
 */
class AssistantTurnChanged implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(public AssistantTurn $turn) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel(Channels::assistantSession($this->turn->session));
    }

    public function broadcastAs(): string
    {
        return 'turn.changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'turn' => [
                'id' => $this->turn->id,
                'assistant_session_id' => $this->turn->assistant_session_id,
                'status' => $this->turn->status->value,
                'user_message_id' => $this->turn->user_message_id,
                'assistant_message_id' => $this->turn->assistant_message_id,
                'error' => $this->turn->error,
            ],
        ];
    }
}
