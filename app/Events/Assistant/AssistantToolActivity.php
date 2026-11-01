<?php

namespace App\Events\Assistant;

use App\Broadcasting\Channels;
use App\Models\Assistant\AssistantTurn;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The assistant started or finished a tool call: shown live as "Searching
 * the web…". Carries the tool's name only; arguments and results can be
 * large and stay in the stored reply.
 */
class AssistantToolActivity implements ShouldBroadcastNow
{
    use Dispatchable;

    public const string STARTED = 'started';

    public const string FINISHED = 'finished';

    public function __construct(
        public AssistantTurn $turn,
        public string $toolCallId,
        public string $tool,
        public string $phase,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel(Channels::assistantSession($this->turn->session));
    }

    public function broadcastAs(): string
    {
        return 'turn.tool';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'turn_id' => $this->turn->id,
            'tool_call_id' => $this->toolCallId,
            'tool' => $this->tool,
            'phase' => $this->phase,
        ];
    }
}
