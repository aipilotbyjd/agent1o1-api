<?php

namespace App\Http\Controllers\Api\Public\V1\Agents;

use App\Actions\Agents\ResolveAgentActionsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Agents\DecideAgentActionsRequest;
use App\Http\Resources\Api\Public\V1\AgentActionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentAction;
use App\Models\Agents\AgentSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Lets an API caller run their own approval step: list a conversation's
 * actions (a reply marked `awaiting_approval` is on hold until its pending
 * ones are decided) and decide them. The API key is the authority (it is
 * scoped to one workspace and granted `agents:invoke`) so there is no
 * per-person approver check here, except that an action whose tool rule
 * names its approvers is theirs alone to decide. Decided turns resume on
 * the queue.
 */
class AgentActionController extends Controller
{
    public function __construct(private readonly ResolveAgentActionsAction $resolve) {}

    public function index(Request $request, Agent $agent, AgentSession $session)
    {
        $this->ensureSession($request, $agent, $session);

        return ApiResponse::success(['actions' => AgentActionResource::collection($session->actions()->oldest('id')->get())]);
    }

    public function decide(DecideAgentActionsRequest $request, Agent $agent, AgentSession $session)
    {
        $this->ensureSession($request, $agent, $session);

        $decisions = collect($request->decisions())->map(fn (array $decision): array => [...$decision, 'remember' => false])->all();

        // `!=` alone never matches an action with no conversation (SQL
        // NULL), which would let one from any workspace through.
        $foreign = AgentAction::query()
            ->whereKey(array_column($decisions, 'action_id'))
            ->where(fn (Builder $query) => $query->whereNull('agent_session_id')->orWhere('agent_session_id', '!=', $session->id))
            ->exists();

        abort_if($foreign, 404);

        // Like a chat button, an API key can't stand in for the people a tool
        // rule names as its approvers.
        $restricted = AgentAction::query()
            ->whereKey(array_column($decisions, 'action_id'))
            ->get()
            ->contains(fn (AgentAction $action): bool => $action->hasNamedApprovers());

        abort_if($restricted, 403, 'Only the approvers named for this tool can decide it, in the app.');

        $decided = $this->resolve->execute(null, $decisions, 'api');

        return ApiResponse::success(['actions' => AgentActionResource::collection($decided)], 'Decisions recorded.');
    }

    private function ensureSession(Request $request, Agent $agent, AgentSession $session): void
    {
        $this->ensureBelongsToWorkspace($this->apiKeyWorkspace($request), $agent);
        abort_if($session->agent_id !== $agent->id, 404);
    }
}
