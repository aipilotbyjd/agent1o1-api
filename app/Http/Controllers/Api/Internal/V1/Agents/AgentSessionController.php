<?php

namespace App\Http\Controllers\Api\Internal\V1\Agents;

use App\Actions\Agents\CreateAgentSessionAction;
use App\Actions\Agents\SendAgentMessageAction;
use App\Enums\Agents\AutonomyMode;
use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Agents\SendAgentMessageRequest;
use App\Http\Requests\Api\Internal\V1\Agents\StoreAgentSessionRequest;
use App\Http\Requests\Api\Internal\V1\Agents\UpdateAgentSessionRequest;
use App\Http\Resources\Api\Internal\V1\Agents\AgentMessageResource;
use App\Http\Resources\Api\Internal\V1\Agents\AgentSessionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentSession;
use App\Models\Workspaces\Workspace;

class AgentSessionController extends Controller
{
    public function __construct(
        private readonly CreateAgentSessionAction $createSession,
        private readonly SendAgentMessageAction $sendMessage,
    ) {}

    public function index(Workspace $workspace, Agent $agent)
    {
        $this->requirePermission(Permission::AgentView);
        $this->ensureBelongsToWorkspace($workspace, $agent);

        return ApiResponse::success([
            'sessions' => AgentSessionResource::collection($agent->sessions()->whereNull('parent_session_id')->withCount(['messages', 'pendingActions'])->latest()->get()),
        ]);
    }

    public function store(StoreAgentSessionRequest $request, Workspace $workspace, Agent $agent)
    {
        $this->requirePermission(Permission::AgentChat);
        $this->ensureBelongsToWorkspace($workspace, $agent);

        $session = $this->createSession->execute($agent, $request->user(), $request->validated('title'));

        return ApiResponse::created(['session' => AgentSessionResource::make($session)], 'Session created successfully.');
    }

    public function show(Workspace $workspace, Agent $agent, AgentSession $session)
    {
        $this->requirePermission(Permission::AgentView);
        $this->ensureBelongsToWorkspace($workspace, $agent);
        abort_if($session->agent_id !== $agent->id, 404);

        return ApiResponse::success([
            'session' => AgentSessionResource::make($session->load('messages.attachments.agent')),
        ]);
    }

    /**
     * Anyone who can chat may make a conversation stricter than its agent
     * (a tighter mode, or Test run on). Loosening it (a freer mode, or Test
     * run off when the agent has it on) is an agent setting in disguise,
     * so it takes the right to manage the agent.
     */
    public function update(UpdateAgentSessionRequest $request, Workspace $workspace, Agent $agent, AgentSession $session)
    {
        $this->requirePermission(Permission::AgentChat);
        $this->ensureBelongsToWorkspace($workspace, $agent);
        abort_if($session->agent_id !== $agent->id, 404);

        $mode = AutonomyMode::tryFrom((string) $request->validated('autonomy_mode'));
        $loosensMode = $mode !== null && $agent->autonomy_mode->isStricterThan($mode);
        $loosensTestRun = $request->has('test_mode') && $agent->test_mode && $request->validated('test_mode') === false;

        if ($loosensMode || $loosensTestRun) {
            $this->requirePermission(Permission::AgentManage);
        }

        $session->update($request->validated());

        return ApiResponse::success(['session' => AgentSessionResource::make($session)], 'Session updated successfully.');
    }

    public function destroy(Workspace $workspace, Agent $agent, AgentSession $session)
    {
        $this->requirePermission(Permission::AgentManage);
        $this->ensureBelongsToWorkspace($workspace, $agent);
        abort_if($session->agent_id !== $agent->id, 404);

        $session->delete();

        return ApiResponse::noContent();
    }

    /**
     * Paginated transcript. `show()` eager-loads the whole conversation,
     * which is fine for a fresh session and wrong for a long-running one:
     * this is what a chat window pages backwards through.
     */
    public function messages(Workspace $workspace, Agent $agent, AgentSession $session)
    {
        $this->requirePermission(Permission::AgentView);
        $this->ensureBelongsToWorkspace($workspace, $agent);
        abort_if($session->agent_id !== $agent->id, 404);

        $perPage = (int) request()->integer('per_page', 50);

        return ApiResponse::paginated(
            AgentMessageResource::collection(
                $session->messages()->with(['attachments.agent', 'skill'])->oldest('id')->paginate(min(max($perPage, 1), 200)),
            ),
        );
    }

    public function sendMessage(SendAgentMessageRequest $request, Workspace $workspace, Agent $agent, AgentSession $session)
    {
        $this->requirePermission(Permission::AgentChat);
        $this->ensureBelongsToWorkspace($workspace, $agent);
        abort_if($session->agent_id !== $agent->id, 404);

        $reply = $this->sendMessage->execute($session, $request->validated('message'), attachments: $request->attachmentFiles(), skill: $request->chosenSkill($agent), replacing: $request->validated('replaces_message_id'));

        return ApiResponse::success(['message' => AgentMessageResource::make($reply)]);
    }
}
