<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Workflow\StoreWorkflowShareRequest;
use App\Http\Resources\V1\WorkflowShareResource;
use App\Models\Workflow;
use App\Models\WorkflowShare;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WorkflowShareController extends Controller
{
    public function index(Request $request, Workspace $workspace, Workflow $workflow): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::WorkflowView)) {
            return $denied;
        }

        return $this->successResponse(
            'Workflow shares retrieved.',
            WorkflowShareResource::collection($workflow->shares()->latest()->get()),
        );
    }

    public function store(StoreWorkflowShareRequest $request, Workspace $workspace, Workflow $workflow): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::WorkflowUpdate)) {
            return $denied;
        }

        $share = $workflow->shares()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $request->user()->id,
            'token' => Str::random(48),
            'allow_clone' => $request->validated('allow_clone') ?? true,
            'expires_at' => $request->validated('expires_at'),
        ]);

        return $this->successResponse('Share link created.', new WorkflowShareResource($share), 201);
    }

    public function destroy(Request $request, Workspace $workspace, Workflow $workflow, WorkflowShare $share): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::WorkflowUpdate)) {
            return $denied;
        }

        $share->delete();

        return $this->successResponse('Share link revoked.');
    }
}
