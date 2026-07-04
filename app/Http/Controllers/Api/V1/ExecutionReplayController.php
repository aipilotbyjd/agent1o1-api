<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Execution\StoreReplayPackRequest;
use App\Http\Resources\V1\ExecutionReplayPackResource;
use App\Http\Resources\V1\ExecutionResource;
use App\Models\Execution;
use App\Models\ExecutionReplayPack;
use App\Models\Workspace;
use App\Services\ExecutionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExecutionReplayController extends Controller
{
    public function __construct(private readonly ExecutionService $executionService) {}

    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::ExecutionView)) {
            return $denied;
        }

        $packs = ExecutionReplayPack::query()
            ->where('workspace_id', $workspace->id)
            ->when($request->query('workflow_id'), fn ($q, $id) => $q->where('workflow_id', $id))
            ->latest()
            ->paginate((int) $request->query('per_page', 20));

        return $this->paginatedResponse('Replay packs retrieved.', ExecutionReplayPackResource::collection($packs));
    }

    /**
     * Capture a reproducible snapshot (graph version + trigger data) of an execution.
     */
    public function store(StoreReplayPackRequest $request, Workspace $workspace, Execution $execution): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::ExecutionManage)) {
            return $denied;
        }

        $execution->loadMissing('workflow.currentVersion');
        $version = $execution->workflow?->currentVersion;

        $pack = ExecutionReplayPack::create([
            'workspace_id' => $workspace->id,
            'workflow_id' => $execution->workflow_id,
            'execution_id' => $execution->id,
            'created_by' => $request->user()->id,
            'label' => $request->validated('label'),
            'version_snapshot' => [
                'version_number' => $version?->version_number,
                'nodes' => $version?->nodes_data ?? [],
                'edges' => $version?->edges_data ?? [],
            ],
            'trigger_data' => $execution->trigger_data,
        ]);

        return $this->successResponse('Replay pack created.', new ExecutionReplayPackResource($pack), 201);
    }

    /**
     * Re-run the workflow using the pack's captured trigger data.
     */
    public function replay(Request $request, Workspace $workspace, ExecutionReplayPack $replayPack): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::WorkflowExecute)) {
            return $denied;
        }

        $workflow = $replayPack->workflow;

        if (! $workflow) {
            return $this->errorResponse('Source workflow no longer exists.', 422);
        }

        $execution = $this->executionService->trigger(
            $workflow,
            $request->user(),
            $replayPack->trigger_data ?? [],
        );

        return $this->successResponse('Replay queued.', [
            'execution' => new ExecutionResource($execution),
            'channel' => "private-execution.{$execution->id}",
        ], 202);
    }
}
