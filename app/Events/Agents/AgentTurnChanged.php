<?php

namespace App\Events\Agents;

use App\Broadcasting\Channels;
use App\Models\Agents\AgentSession;
use App\Models\Runs\Run;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A chat turn moved: started streaming, paused for approvals, finished or
 * failed. Anything but `running` is final for this stretch of the turn:
 * the chat swaps its live draft for the stored message (`message_id`) and,
 * for `awaiting_approval`, fetches the waiting actions
 * (`pending_action_ids`). `error` is a message that is safe to show; the
 * raw failure is on the run.
 */
class AgentTurnChanged implements ShouldBroadcastNow
{
    use Dispatchable;

    /**
     * The payload is captured now, not when it is sent: the run keeps
     * changing as the turn settles, and this event says what it was at the
     * moment it was announced.
     *
     * @var array<string, mixed>
     */
    public readonly array $turn;

    public function __construct(
        public readonly AgentSession $session,
        Run $run,
        ?string $error = null,
    ) {
        $this->turn = array_filter([
            'run_id' => $run->id,
            'agent_session_id' => $session->id,
            'status' => $run->status->value,
            'message_id' => $run->output['message_id'] ?? null,
            'pending_action_ids' => $run->output['pending_action_ids'] ?? null,
            'error' => $error,
        ], fn (mixed $value): bool => $value !== null);
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel(Channels::agentSession($this->session));
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
        return ['turn' => $this->turn];
    }
}
