<?php

namespace App\Http\Controllers\Api\Internal\V1\Agents;

use App\Enums\Agents\SessionEvaluationGrade;
use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Internal\V1\Agents\AgentSessionEvaluationResource;
use App\Http\Responses\ApiResponse;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentSession;
use App\Models\Agents\AgentSessionEvaluation;
use App\Models\Workspaces\Workspace;
use App\Services\Agents\SessionEvaluator;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AgentSessionEvaluationController extends Controller
{
    public function __construct(private readonly SessionEvaluator $evaluator) {}

    public function index(Request $request, Workspace $workspace, Agent $agent)
    {
        $this->requirePermission(Permission::AgentView);
        $this->ensureBelongsToWorkspace($workspace, $agent);

        $request->validate(['grade' => ['sometimes', Rule::enum(SessionEvaluationGrade::class)]]);

        $evaluations = $agent->sessionEvaluations()
            ->when($request->filled('grade'), fn ($query) => $query->where('grade', $request->string('grade')))
            ->latest()
            ->paginate(min(max($request->integer('per_page', 20), 1), 100));

        return ApiResponse::paginated(AgentSessionEvaluationResource::collection($evaluations));
    }

    public function show(Workspace $workspace, Agent $agent, AgentSessionEvaluation $evaluation)
    {
        $this->requirePermission(Permission::AgentView);
        $this->ensureBelongsToWorkspace($workspace, $agent);
        abort_if($evaluation->agent_id !== $agent->id, 404);

        return ApiResponse::success(['evaluation' => AgentSessionEvaluationResource::make($evaluation)]);
    }

    /**
     * Manually (re-)evaluates one session, for backfilling conversations
     * from before evaluations were enabled, re-grading after criteria
     * changed, or retrying a failed evaluation. Runs immediately, without
     * the debounce `ScheduleSessionEvaluation` applies to the automatic
     * path, since a person asking for this explicitly isn't at risk of
     * catching a conversation mid-turn.
     */
    public function run(Workspace $workspace, Agent $agent, AgentSession $session)
    {
        $this->requirePermission(Permission::AgentManage);
        $this->requirePermission(Permission::AgentChat);
        $this->ensureBelongsToWorkspace($workspace, $agent);
        abort_if($session->agent_id !== $agent->id, 404);

        $evaluation = $this->evaluator->evaluate($session);

        abort_if($evaluation === null, 422, 'Evaluations are not enabled for this agent.');

        return ApiResponse::success(
            ['evaluation' => AgentSessionEvaluationResource::make($evaluation)],
            'Session evaluated.',
        );
    }
}
