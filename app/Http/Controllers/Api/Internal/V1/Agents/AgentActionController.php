<?php

namespace App\Http\Controllers\Api\Internal\V1\Agents;

use App\Actions\Agents\ResolveAgentActionsAction;
use App\Enums\Agents\AgentActionStatus;
use App\Enums\RunStatus;
use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Agents\DecideAgentActionsRequest;
use App\Http\Resources\Api\Internal\V1\Agents\AgentActionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentAction;
use App\Models\Agents\AgentSession;
use App\Models\Workspaces\Workspace;
use App\Services\Agents\Approvals\ActionResumer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * An agent's action log (everything it did or tried to do that changes
 * something, newest first) and one conversation's slice of it, which is
 * what a chat renders its approval cards from (and decides them from). A subagent's actions are
 * included in its parent conversation's list, since that's where a person
 * is watching.
 */
class AgentActionController extends Controller
{
    public function __construct(
        private readonly ResolveAgentActionsAction $resolve,
        private readonly ActionResumer $resumer,
    ) {}

    public function index(Request $request, Workspace $workspace, Agent $agent)
    {
        $this->requirePermission(Permission::AgentView);
        $this->ensureBelongsToWorkspace($workspace, $agent);

        return ApiResponse::paginated(AgentActionResource::collection(
            $this->filtered($request, $agent->actions()->getQuery())->with('session')->latest('id')->paginate(50),
        ));
    }

    public function sessionIndex(Request $request, Workspace $workspace, Agent $agent, AgentSession $session)
    {
        $this->requirePermission(Permission::AgentView);
        $this->ensureBelongsToWorkspace($workspace, $agent);
        abort_if($session->agent_id !== $agent->id, 404);

        $query = AgentAction::query()->where(fn (Builder $query) => $query
            ->where('agent_session_id', $session->id)
            ->orWhereIn('agent_session_id', AgentSession::query()->where('parent_session_id', $session->id)->select('id')));

        return ApiResponse::success([
            'actions' => AgentActionResource::collection($this->filtered($request, $query)->with(['agent', 'session'])->oldest('id')->get()),
        ]);
    }

    /**
     * Deciding from an open chat: records the decisions and, if that leaves
     * the conversation's paused turn fully decided, queues it to carry on:
     * the person watches the agent continue over the conversation's channel
     * (`AgentTurnBroadcaster`), the same as for a sent message.
     */
    public function decide(DecideAgentActionsRequest $request, Workspace $workspace, Agent $agent, AgentSession $session)
    {
        $this->requirePermission(Permission::AgentChat);
        $this->ensureBelongsToWorkspace($workspace, $agent);
        abort_if($session->agent_id !== $agent->id, 404);

        // Only this conversation's actions, and its subagents': the same
        // set `sessionIndex()` shows it.
        $sessionIds = AgentSession::query()->where('parent_session_id', $session->id)->pluck('id')->push($session->id);
        $decisionIds = array_column($request->decisions(), 'action_id');

        abort_if(AgentAction::query()->whereKey($decisionIds)->whereIn('agent_session_id', $sessionIds)->count() !== count($decisionIds), 404);

        $decided = $this->resolve->execute($request->user(), $request->decisions(), resume: false);

        // Whether this conversation's own paused turn carries on now: the
        // chat follows it over the channel; subagents' turns resume quietly.
        $run = $session->runs()->where('status', RunStatus::AwaitingApproval)->latest('id')->first();
        $resumesNow = $run !== null && $this->resumer->runIsReady($run);

        $this->resumer->resumeReady($decided);

        return ApiResponse::success([
            'actions' => AgentActionResource::collection($decided),
            'resumed' => $resumesNow,
            'run_id' => $resumesNow ? $run->id : null,
        ], 'Decisions recorded.');
    }

    /**
     * @param  Builder<AgentAction>  $query
     * @return Builder<AgentAction>
     */
    private function filtered(Request $request, Builder $query): Builder
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(AgentActionStatus::class)],
            'tool_name' => ['nullable', 'string', 'max:255'],
        ]);

        return $query
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['tool_name'] ?? null, fn (Builder $query, string $tool) => $query->where('tool_name', $tool));
    }
}
