<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Workflow\ExecuteWorkflowRequest;
use App\Http\Resources\V1\AiAgentStepResource;
use App\Http\Resources\V1\ExecutionLogResource;
use App\Http\Resources\V1\ExecutionNodeResource;
use App\Http\Resources\V1\RunResource;
use App\Models\Agent;
use App\Models\Run;
use App\Models\Workflow;
use App\Models\Workspace;
use App\Services\Billing\CreditService;
use App\Services\RunService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Unified API for runs — workflow executions and agent runs — over the single
 * Run model. Shared lifecycle actions (show, delete, cancel, logs) work for any
 * run; type-specific actions are capability-gated: `nodes`/`retry` are workflow
 * only, `steps` is agent only, returning 422 on the wrong runnable type.
 */
class RunController extends Controller
{
    public function __construct(
        private readonly RunService $runs,
        private readonly CreditService $creditService,
    ) {}

    /**
     * Start a manual workflow execution. Agent runs are opened by the agent
     * runtime (conversation / trigger), so this action is workflow-only.
     */
    public function store(ExecuteWorkflowRequest $request, Workspace $workspace, Workflow $workflow): JsonResponse
    {
        if (! $workflow->currentVersion || empty($workflow->currentVersion->nodes_data)) {
            return $this->errorResponse('Workflow has no nodes to execute.', 422);
        }

        // Fail fast with a clear 402 before queuing if the workspace is out of credits.
        // Authoritative metering happens under a lock when the run starts running.
        $this->creditService->checkCredits(
            $workspace,
            (int) config('billing.credits_per_execution', 1),
        );

        $run = $this->runs->trigger(
            $workflow,
            $request->user(),
            $request->validated('trigger_data') ?? [],
        );

        return $this->successResponse(
            'Run queued. Subscribe to the run channel for real-time updates.',
            [
                'run' => new RunResource($run),
                'channel' => "private-execution.{$run->id}",
            ],
            202,
        );
    }

    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        if ($forbidden = $this->requirePermission(Permission::ExecutionView)) {
            return $forbidden;
        }

        $runs = $workspace->runs()
            ->when($request->query('runnable_type'), fn ($q, $type) => $q->where('runnable_type', $type))
            ->when($request->query('runnable_id'), fn ($q, $id) => $q->where('runnable_id', $id))
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('from'), fn ($q, $from) => $q->where('created_at', '>=', $from))
            ->when($request->query('to'), fn ($q, $to) => $q->where('created_at', '<=', $to))
            ->latest()
            ->paginate((int) $request->query('per_page', 20));

        return $this->paginatedResponse('Runs retrieved.', RunResource::collection($runs));
    }

    public function show(Request $request, Workspace $workspace, Run $run): JsonResponse
    {
        if ($forbidden = $this->requirePermission(Permission::ExecutionView)) {
            return $forbidden;
        }

        $this->authorizeRun($run, $workspace);

        $run->load('runnable');

        if ($run->isForWorkflow()) {
            $run->load('triggeredBy');
        } else {
            $run->load(['steps', 'internalRuns'])->loadCount('steps');
        }

        return $this->successResponse('Run retrieved.', new RunResource($run));
    }

    public function nodes(Request $request, Workspace $workspace, Run $run): JsonResponse
    {
        if ($forbidden = $this->requirePermission(Permission::ExecutionView)) {
            return $forbidden;
        }

        $this->authorizeRun($run, $workspace);

        if ($guard = $this->ensureWorkflow($run)) {
            return $guard;
        }

        return $this->successResponse('Run nodes retrieved.', ExecutionNodeResource::collection($run->nodes));
    }

    public function steps(Request $request, Workspace $workspace, Run $run): JsonResponse
    {
        if ($forbidden = $this->requirePermission(Permission::ExecutionView)) {
            return $forbidden;
        }

        $this->authorizeRun($run, $workspace);

        if ($guard = $this->ensureAgent($run)) {
            return $guard;
        }

        return $this->successResponse('Run steps retrieved.', AiAgentStepResource::collection($run->steps));
    }

    public function logs(Request $request, Workspace $workspace, Run $run): JsonResponse
    {
        if ($forbidden = $this->requirePermission(Permission::ExecutionView)) {
            return $forbidden;
        }

        $this->authorizeRun($run, $workspace);

        $logs = $run->logs()
            ->when($request->query('level'), fn ($q, $level) => $q->where('level', $level))
            ->orderBy('logged_at')
            ->paginate((int) $request->query('per_page', 100));

        return $this->paginatedResponse('Run logs retrieved.', ExecutionLogResource::collection($logs));
    }

    public function retry(Request $request, Workspace $workspace, Run $run): JsonResponse
    {
        if ($forbidden = $this->requirePermission(Permission::ExecutionManage)) {
            return $forbidden;
        }

        $this->authorizeRun($run, $workspace);

        if ($guard = $this->ensureWorkflow($run)) {
            return $guard;
        }

        if (! $run->isFailed()) {
            return $this->errorResponse('Only failed runs can be retried.', 422);
        }

        $retry = $this->runs->retry($run, $request->user());

        return $this->successResponse(
            'Retry queued.',
            ['run' => new RunResource($retry), 'channel' => "private-execution.{$retry->id}"],
            202,
        );
    }

    public function cancel(Request $request, Workspace $workspace, Run $run): JsonResponse
    {
        if ($forbidden = $this->requirePermission(Permission::ExecutionManage)) {
            return $forbidden;
        }

        $this->authorizeRun($run, $workspace);

        if ($run->status->isTerminal()) {
            return $this->errorResponse('Run already finished.', 422);
        }

        return $this->successResponse('Run cancelled.', new RunResource($this->runs->cancel($run)));
    }

    public function destroy(Request $request, Workspace $workspace, Run $run): JsonResponse
    {
        if ($forbidden = $this->requirePermission(Permission::ExecutionManage)) {
            return $forbidden;
        }

        $this->authorizeRun($run, $workspace);

        if (! $run->status->isTerminal()) {
            return $this->errorResponse('Cannot delete a running run. Cancel it first.', 422);
        }

        $run->delete();

        return $this->successResponse('Run deleted.');
    }

    // ── Agent-scoped views (nested under agents/{agent}/runs) ───────────

    /**
     * Run history for a single agent — the agent-scoped slice of the unified
     * run surface, gated by agent (not execution) view permission.
     */
    public function indexForAgent(Request $request, Workspace $workspace, Agent $agent): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentView)) {
            return $denied;
        }

        $runs = $agent->runs()
            ->withCount('steps')
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('source'), fn ($q, $source) => $q->where('source', $source))
            ->latest()
            ->paginate((int) $request->query('per_page', 25));

        return $this->paginatedResponse('Agent runs retrieved.', RunResource::collection($runs));
    }

    public function showForAgent(Request $request, Workspace $workspace, Agent $agent, Run $run): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentView)) {
            return $denied;
        }

        if ($run->agent_id !== $agent->id) {
            return $this->errorResponse('Agent run not found.', 404);
        }

        return $this->successResponse(
            'Agent run retrieved.',
            new RunResource($run->load(['steps', 'internalRuns'])->loadCount('steps')),
        );
    }

    private function authorizeRun(Run $run, Workspace $workspace): void
    {
        abort_unless($run->workspace_id === $workspace->id, 404);
    }

    private function ensureWorkflow(Run $run): ?JsonResponse
    {
        return $run->isForWorkflow()
            ? null
            : $this->errorResponse('This action is only available for workflow runs.', 422);
    }

    private function ensureAgent(Run $run): ?JsonResponse
    {
        return $run->isForAgent()
            ? null
            : $this->errorResponse('This action is only available for agent runs.', 422);
    }
}
