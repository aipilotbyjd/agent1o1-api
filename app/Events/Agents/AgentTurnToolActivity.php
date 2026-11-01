<?php

namespace App\Events\Agents;

use App\Broadcasting\Channels;
use App\Models\Agents\AgentSession;
use App\Models\Runs\Run;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The agent started or finished a tool call: shown live as "Searching the
 * web…". Arguments and output ride along only when small: an event over
 * Reverb's 10 KB limit is simply lost, so `AgentTurnBroadcaster` leaves out
 * arguments past `MAX_ARGUMENTS_BYTES` and cuts output at `MAX_OUTPUT_CHARS`.
 * The full call is on the stored reply, which the chat fetches once the turn
 * settles (`AgentTurnChanged`).
 *
 * A started subagent's `task_id` rides along so the chat can track it live.
 */
class AgentTurnToolActivity implements ShouldBroadcastNow
{
    use Dispatchable;

    public const string STARTED = 'started';

    public const string FINISHED = 'finished';

    public const int MAX_ARGUMENTS_BYTES = 1500;

    public const int MAX_OUTPUT_CHARS = 1000;

    public function __construct(
        public readonly AgentSession $session,
        public readonly Run $run,
        public readonly string $toolCallId,
        public readonly string $tool,
        public readonly string $phase,
        public readonly ?bool $successful = null,
        public readonly bool $denied = false,
        public readonly ?string $subagentTaskId = null,
        public readonly ?array $arguments = null,
        public readonly ?string $output = null,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel(Channels::agentSession($this->session));
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
        return array_filter([
            'run_id' => $this->run->id,
            'tool_call_id' => $this->toolCallId,
            'tool' => $this->tool,
            'phase' => $this->phase,
            'successful' => $this->successful,
            'denied' => $this->denied ?: null,
            'subagent_task_id' => $this->subagentTaskId,
            'arguments' => $this->arguments,
            'output' => $this->output,
        ], fn (mixed $value): bool => $value !== null);
    }
}
