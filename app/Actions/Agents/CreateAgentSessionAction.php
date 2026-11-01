<?php

namespace App\Actions\Agents;

use App\Models\Agents\Agent;
use App\Models\Agents\AgentSession;
use App\Models\User;
use App\Services\Agents\AgentVersioner;

class CreateAgentSessionAction
{
    public function __construct(private readonly AgentVersioner $versioner) {}

    /**
     * The session is pinned to the agent's current version at creation, so
     * every turn of this conversation runs the behavior it started with:
     * see `AgentSession::pinnedAgent()`.
     */
    public function execute(Agent $agent, ?User $user = null, ?string $title = null): AgentSession
    {
        return $agent->sessions()->create([
            'workspace_id' => $agent->workspace_id,
            'agent_version_id' => $this->versioner->currentVersion($agent)->id,
            'user_id' => $user?->id,
            'title' => $title,
        ]);
    }
}
