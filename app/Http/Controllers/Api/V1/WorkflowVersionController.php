<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\WorkflowVersionResource;
use App\Models\Workflow;
use App\Models\Workspace;
use App\Services\WorkflowVersionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkflowVersionController extends Controller
{
    public function __construct(private readonly WorkflowVersionService $versionService) {}

    public function index(Request $request, Workspace $workspace, Workflow $workflow): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::WorkflowView)) {
            return $denied;
        }

        $versions = $workflow->versions()->paginate((int) $request->query('per_page', 20));

        return $this->paginatedResponse('Workflow versions retrieved.', WorkflowVersionResource::collection($versions));
    }

    public function show(Request $request, Workspace $workspace, Workflow $workflow, string $version): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::WorkflowView)) {
            return $denied;
        }

        $model = $workflow->versions()->findOrFail($version);

        return $this->successResponse('Workflow version retrieved.', new WorkflowVersionResource($model));
    }

    public function publish(Request $request, Workspace $workspace, Workflow $workflow, string $version): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::WorkflowUpdate)) {
            return $denied;
        }

        if ($workflow->is_locked) {
            return $this->errorResponse('Workflow is locked.', 423);
        }

        $model = $workflow->versions()->findOrFail($version);
        $published = $this->versionService->publish($workflow, $model, $request->user());

        return $this->successResponse('Workflow version published.', new WorkflowVersionResource($published));
    }

    public function rollback(Request $request, Workspace $workspace, Workflow $workflow, string $version): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::WorkflowUpdate)) {
            return $denied;
        }

        if ($workflow->is_locked) {
            return $this->errorResponse('Workflow is locked.', 423);
        }

        $model = $workflow->versions()->findOrFail($version);
        $clone = $this->versionService->rollback($workflow, $model);

        return $this->successResponse('Rolled back to version.', new WorkflowVersionResource($clone), 201);
    }

    public function diff(Request $request, Workspace $workspace, Workflow $workflow, string $from, string $to): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::WorkflowView)) {
            return $denied;
        }

        $fromVersion = $workflow->versions()->findOrFail($from);
        $toVersion = $workflow->versions()->findOrFail($to);

        return $this->successResponse('Version diff computed.', $this->versionService->diff($fromVersion, $toVersion));
    }
}
