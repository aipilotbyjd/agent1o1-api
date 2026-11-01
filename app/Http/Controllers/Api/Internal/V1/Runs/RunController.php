<?php

namespace App\Http\Controllers\Api\Internal\V1\Runs;

use App\Actions\Workflows\CancelRunAction;
use App\Actions\Workflows\RetryRunAction;
use App\Actions\Workflows\StartWorkflowRunAction;
use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Runs\IndexRunsRequest;
use App\Http\Requests\Api\Internal\V1\Runs\RetryRunRequest;
use App\Http\Requests\Api\Internal\V1\Runs\StartRunRequest;
use App\Http\Resources\Api\Internal\V1\Runs\RunResource;
use App\Http\Responses\ApiResponse;
use App\Models\Agents\AgentEvalRun;
use App\Models\Agents\AgentMessage;
use App\Models\Agents\AgentSession;
use App\Models\Agents\AgentSessionEvaluation;
use App\Models\Agents\ReflectionRun;
use App\Models\Runs\Run;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use Illuminate\Database\Eloquent\Builder;

class RunController extends Controller
{
    public function __construct(
        private readonly StartWorkflowRunAction $startWorkflowRun,
        private readonly CancelRunAction $cancelRun,
        private readonly RetryRunAction $retryRun,
    ) {}

    public function index(IndexRunsRequest $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::RunView);

        $runs = $workspace->runs()
            ->when($request->validated('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->validated('workflow_id'), fn ($query, $workflowId) => $query->where('workflow_id', $workflowId))
            ->when($request->validated('agent_id'), fn ($query, $agentId) => $this->forAgent($query, $agentId))
            ->when($request->validated('runnable_type'), fn ($query, $type) => $query->where('runnable_type', $type))
            ->when($request->validated('trigger_type'), fn ($query, $type) => $query->where('trigger_type', $type))
            ->when($request->validated('exclude_trigger_type'), fn ($query, $type) => $query->where('trigger_type', '!=', $type))
            ->with(['nodeRuns.creditTransaction', ...Run::withOwnerNames()])
            ->latest()
            ->paginate($request->validated('per_page') ?? 25)
            ->withQueryString();

        return ApiResponse::paginated(RunResource::collection($runs));
    }

    /**
     * Everything run on an agent's behalf: its chat turns, reflections, chat
     * grading and eval suites. None of these point at the agent directly
     * (each run belongs to the chat, review or suite) so it's matched through
     * that runnable.
     */
    private function forAgent(Builder $query, string $agentId): Builder
    {
        return $query->whereHasMorph(
            'runnable',
            [AgentSession::class, ReflectionRun::class, AgentSessionEvaluation::class, AgentEvalRun::class],
            fn (Builder $runnable, string $type) => $type === AgentEvalRun::class
                ? $runnable->whereHas('suite', fn (Builder $suite) => $suite->where('agent_id', $agentId))
                : $runnable->where('agent_id', $agentId),
        );
    }

    public function show(Workspace $workspace, Run $run)
    {
        $this->requirePermission(Permission::RunView);
        $this->ensureBelongsToWorkspace($workspace, $run);

        $run->load(['nodeRuns.creditTransaction', ...Run::withOwnerNames()]);

        if ($run->runnable instanceof AgentSession) {
            $run->setRelation('agentReply', $this->agentReplyFor($run));
        }

        return ApiResponse::success(['run' => RunResource::make($run)]);
    }

    /**
     * A chat turn's `Run` records the reply it produced in `output.message_id`:
     * see `AgentRunner::completeTurn()`. A turn that failed has none.
     */
    private function agentReplyFor(Run $run): ?AgentMessage
    {
        $messageId = $run->output['message_id'] ?? null;

        return $messageId
            ? $run->runnable->messages()->with('attachments.agent')->find($messageId)
            : null;
    }

    public function store(StartRunRequest $request, Workspace $workspace, Workflow $workflow)
    {
        $this->requirePermission(Permission::RunTrigger);
        $this->ensureBelongsToWorkspace($workspace, $workflow);

        $run = $this->startWorkflowRun->execute(
            $workflow,
            $request->validated('input') ?? [],
            $request->user(),
        );

        return ApiResponse::success(['run' => RunResource::make($run)], 'Run started.', 202);
    }

    /**
     * Cancelling any run in a tree cancels the whole tree: see
     * `CancelRunAction`. Gated on `RunTrigger` rather than `RunView`: stopping
     * work is the same class of act as starting it.
     */
    public function cancel(Workspace $workspace, Run $run)
    {
        $this->requirePermission(Permission::RunTrigger);
        $this->ensureBelongsToWorkspace($workspace, $run);

        $cancelled = $this->cancelRun->execute($run, request()->user());

        return ApiResponse::success(['run' => RunResource::make($cancelled->load('nodeRuns.creditTransaction'))], 'Run cancelled.');
    }

    public function retry(RetryRunRequest $request, Workspace $workspace, Run $run)
    {
        $this->requirePermission(Permission::RunTrigger);
        $this->ensureBelongsToWorkspace($workspace, $run);

        $retry = $this->retryRun->execute(
            $run,
            $request->user(),
            $request->validated('from_node_key'),
        );

        return ApiResponse::success(['run' => RunResource::make($retry->load('nodeRuns.creditTransaction'))], 'Run retried.', 202);
    }
}
