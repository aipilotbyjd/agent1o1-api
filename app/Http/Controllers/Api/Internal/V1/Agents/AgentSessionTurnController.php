<?php

namespace App\Http\Controllers\Api\Internal\V1\Agents;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Agents\SendAgentMessageRequest;
use App\Http\Responses\ApiResponse;
use App\Jobs\Agents\RunAgentTurnJob;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentSession;
use App\Models\Workspaces\Workspace;
use App\Services\Agents\AgentRunner;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * The same chat turn `AgentSessionController::sendMessage()` runs, delivered
 * over Reverb instead of in the response. The request only opens the turn
 * (credit gate, the user's message, its attachments) so a busy conversation
 * or an empty balance is refused right here; the model call runs on the
 * queue and streams to the conversation's private channel
 * (`Channels::agentSession()`). See `AgentTurnBroadcaster` for the event
 * names; `turn.changed` is always sent last, whether the turn succeeded,
 * paused for approvals or failed.
 *
 * The transcript is persisted whether or not anyone is listening: a
 * dropped socket loses the live view, never the conversation.
 */
class AgentSessionTurnController extends Controller
{
    public function __construct(private readonly AgentRunner $runner) {}

    public function store(SendAgentMessageRequest $request, Workspace $workspace, Agent $agent, AgentSession $session): JsonResponse
    {
        $this->requirePermission(Permission::AgentChat);
        $this->ensureBelongsToWorkspace($workspace, $agent);
        abort_if($session->agent_id !== $agent->id, 404);

        $run = $this->runner->beginTurn($session, $request->validated('message'), attachments: $request->attachmentFiles(), skill: $request->chosenSkill($agent), replacing: $request->validated('replaces_message_id'));

        RunAgentTurnJob::dispatch($run->id);

        return ApiResponse::success([
            'turn' => [
                'run_id' => $run->id,
                'status' => $run->status->value,
                'user_message_id' => $run->input['user_message_id'],
            ],
        ], 'Message sent.', Response::HTTP_ACCEPTED);
    }
}
