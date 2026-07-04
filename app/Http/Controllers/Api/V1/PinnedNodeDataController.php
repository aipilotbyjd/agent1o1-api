<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PinnedData\StorePinnedDataRequest;
use App\Http\Resources\V1\PinnedNodeDataResource;
use App\Models\Workflow;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PinnedNodeDataController extends Controller
{
    public function index(Request $request, Workspace $workspace, Workflow $workflow): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::WorkflowView)) {
            return $denied;
        }

        return $this->successResponse(
            'Pinned data retrieved.',
            PinnedNodeDataResource::collection($workflow->pinnedData()->get()),
        );
    }

    public function store(StorePinnedDataRequest $request, Workspace $workspace, Workflow $workflow): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::WorkflowUpdate)) {
            return $denied;
        }

        $pin = $workflow->pinnedData()->updateOrCreate(
            ['node_id' => $request->validated('node_id')],
            [
                'workspace_id' => $workspace->id,
                'created_by' => $request->user()->id,
                'data' => $request->validated('data'),
            ],
        );

        return $this->successResponse('Node data pinned.', new PinnedNodeDataResource($pin), 201);
    }

    public function destroy(Request $request, Workspace $workspace, Workflow $workflow, string $nodeId): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::WorkflowUpdate)) {
            return $denied;
        }

        $workflow->pinnedData()->where('node_id', $nodeId)->delete();

        return $this->successResponse('Pinned data removed.');
    }
}
