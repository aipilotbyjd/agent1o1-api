<?php

namespace App\Events\Agents;

use App\Broadcasting\BroadcastsReliably;
use App\Broadcasting\Channels;
use App\Models\Agents\AgentAction;
use App\Models\Agents\AgentSession;
use App\Models\Runs\Run;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * A turn paused on actions that need a person: broadcast to the
 * conversation so an open chat shows the approval cards, and picked up by
 * `NotifyAgentActionApprovers` for everyone else. Like `AgentMessageCreated`,
 * it only says which actions are waiting; a listener fetches them.
 */
class AgentActionsRequested implements ShouldBroadcast
{
    use BroadcastsReliably, Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  Collection<int, AgentAction>  $actions
     */
    public function __construct(
        public readonly AgentSession $session,
        public readonly Run $run,
        public readonly Collection $actions,
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
        return 'agent.actions.requested';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'agent_session_id' => $this->session->id,
            'run_id' => $this->run->id,
            'action_ids' => $this->actions->modelKeys(),
        ];
    }
}
