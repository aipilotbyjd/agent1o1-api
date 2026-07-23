<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Execution\StoreReplayPackRequest;
use App\Http\Resources\V1\ExecutionReplayPackResource;
use App\Http\Resources\V1\RunResource;
use App\Models\ExecutionReplayPack;
use App\Models\Run;
use App\Models\Workspace;
use App\Services\RunService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExecutionReplayController extends Controller
{
    public function __construct(private readonly RunService $runs) {}

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
    public function store(StoreReplayPackRequest $request, Workspace $workspace, Run $run): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::ExecutionManage)) {
            return $denied;
        }

        $run->loadMissing('workflow.currentVersion');
        $version = $run->workflow?->currentVersion;

        $pack = ExecutionReplayPack::create([
            'workspace_id' => $workspace->id,
            'workflow_id' => $run->workflow_id,
            'execution_id' => $run->id,
            'created_by' => $request->user()->id,
            'label' => $request->validated('label'),
            'version_snapshot' => [
                'version_number' => $version?->version_number,
                'nodes' => $version?->nodes_data ?? [],
                'edges' => $version?->edges_data ?? [],
            ],
            'trigger_data' => $run->trigger_data,
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

        $run = $this->runs->trigger(
            $workflow,
            $request->user(),
            $replayPack->trigger_data ?? [],
        );

        return $this->successResponse('Replay queued.', [
            'run' => new RunResource($run),
            'channel' => "private-execution.{$run->id}",
        ], 202);
    }
}
