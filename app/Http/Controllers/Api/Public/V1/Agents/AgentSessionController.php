<?php

namespace App\Http\Controllers\Api\Public\V1\Agents;

use App\Actions\Agents\CreateAgentSessionAction;
use App\Actions\Agents\SendAgentMessageAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Public\V1\SendAgentMessageRequest;
use App\Http\Resources\Api\Public\V1\AgentMessageResource;
use App\Http\Resources\Api\Public\V1\AgentSessionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentSession;
use Illuminate\Http\Request;

/**
 * Invoke-only: gated by the `agents:invoke` `ApiKeyAbility`. Read-only
 * discovery lives alongside it in `AgentController`; there is deliberately no
 * agent *authoring* on the Public surface, per docs/STRUCTURE.md's "Public
 * vs. Internal API": external callers run agents, they don't build them.
 */
class AgentSessionController extends Controller
{
    public function __construct(
        private readonly CreateAgentSessionAction $createSession,
        private readonly SendAgentMessageAction $sendMessage,
    ) {}

    public function store(Request $request, Agent $agent)
    {
        $this->ensureBelongsToWorkspace($this->apiKeyWorkspace($request), $agent);

        $session = $this->createSession->execute($agent);

        return ApiResponse::created(['session' => AgentSessionResource::make($session)], 'Session created successfully.');
    }

    public function show(Request $request, Agent $agent, AgentSession $session)
    {
        $this->ensureBelongsToWorkspace($this->apiKeyWorkspace($request), $agent);
        abort_if($session->agent_id !== $agent->id, 404);

        return ApiResponse::success(['session' => AgentSessionResource::make($session->load('messages'))]);
    }

    public function sendMessage(SendAgentMessageRequest $request, Agent $agent, AgentSession $session)
    {
        $this->ensureBelongsToWorkspace($this->apiKeyWorkspace($request), $agent);
        abort_if($session->agent_id !== $agent->id, 404);

        $reply = $this->sendMessage->execute($session, $request->validated('message'));

        return ApiResponse::success(['message' => AgentMessageResource::make($reply)]);
    }
}
