<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Workflow\ExecuteWorkflowRequest;
use App\Http\Resources\V1\ExecutionNodeResource;
use App\Http\Resources\V1\ExecutionResource;
use App\Models\Execution;
use App\Models\Workflow;
use App\Models\Workspace;
use App\Services\Billing\CreditService;
use App\Services\ExecutionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExecutionController extends Controller
{
    public function __construct(
        private readonly ExecutionService $executionService,
        private readonly CreditService $creditService,
    ) {}

    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        if ($forbidden = $this->requirePermission(Permission::ExecutionView)) {
            return $forbidden;
        }

        $executions = $workspace->executions()
            ->with('workflow:id,name,icon,color')
            ->when($request->query('workflow_id'), fn ($q, $id) => $q->where('workflow_id', $id))
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('mode'), fn ($q, $mode) => $q->where('mode', $mode))
            ->when($request->query('from'), fn ($q, $from) => $q->where('created_at', '>=', $from))
            ->when($request->query('to'), fn ($q, $to) => $q->where('created_at', '<=', $to))
            ->latest()
            ->paginate((int) $request->query('per_page', 20));

        return $this->paginatedResponse(
            'Executions retrieved.',
            ExecutionResource::collection($executions),
        );
    }

    public function store(ExecuteWorkflowRequest $request, Workspace $workspace, Workflow $workflow): JsonResponse
    {
        if (! $workflow->currentVersion || empty($workflow->currentVersion->nodes_data)) {
            return $this->errorResponse('Workflow has no nodes to execute.', 422);
        }

        // Fail fast with a clear 402 before queuing if the workspace is out of credits.
        // Authoritative metering happens under a lock when the execution starts running.
        $this->creditService->checkCredits(
            $workspace,
            (int) config('billing.credits_per_execution', 1),
        );

        $execution = $this->executionService->trigger(
            $workflow,
            $request->user(),
            $request->validated('trigger_data') ?? [],
        );

        return $this->successResponse(
            'Execution queued. Subscribe to the execution channel for real-time updates.',
            [
                'execution' => new ExecutionResource($execution),
                'channel' => "private-execution.{$execution->id}",
            ],
            202,
        );
    }

    public function show(Request $request, Workspace $workspace, Execution $execution): JsonResponse
    {
        if ($forbidden = $this->requirePermission(Permission::ExecutionView)) {
            return $forbidden;
        }

        return $this->successResponse(
            'Execution retrieved.',
            new ExecutionResource($execution->load(['workflow:id,name,icon,color', 'triggeredBy'])),
        );
    }

    public function nodes(Request $request, Workspace $workspace, Execution $execution): JsonResponse
    {
        if ($forbidden = $this->requirePermission(Permission::ExecutionView)) {
            return $forbidden;
        }

        return $this->successResponse(
            'Execution nodes retrieved.',
            ExecutionNodeResource::collection($execution->nodes),
        );
    }

    public function retry(Request $request, Workspace $workspace, Execution $execution): JsonResponse
    {
        if ($forbidden = $this->requirePermission(Permission::ExecutionManage)) {
            return $forbidden;
        }

        if (! $execution->isFailed()) {
            return $this->errorResponse('Only failed executions can be retried.', 422);
        }

        $retry = $this->executionService->retry($execution, $request->user());

        return $this->successResponse(
            'Retry queued.',
            [
                'execution' => new ExecutionResource($retry),
                'channel' => "private-execution.{$retry->id}",
            ],
            202,
        );
    }

    public function cancel(Request $request, Workspace $workspace, Execution $execution): JsonResponse
    {
        if ($forbidden = $this->requirePermission(Permission::ExecutionManage)) {
            return $forbidden;
        }

        if ($execution->status->isTerminal()) {
            return $this->errorResponse('Execution already finished.', 422);
        }

        $execution = $this->executionService->cancel($execution);

        return $this->successResponse('Execution cancelled.', new ExecutionResource($execution));
    }

    public function destroy(Request $request, Workspace $workspace, Execution $execution): JsonResponse
    {
        if ($forbidden = $this->requirePermission(Permission::ExecutionManage)) {
            return $forbidden;
        }

        if (! $execution->status->isTerminal()) {
            return $this->errorResponse('Cannot delete a running execution. Cancel it first.', 422);
        }

        $execution->delete();

        return $this->successResponse('Execution deleted.');
    }
}
