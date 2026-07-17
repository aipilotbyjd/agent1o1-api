<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Workflow\DestroyWorkflowRequest;
use App\Http\Requests\Api\V1\Workflow\DuplicateWorkflowRequest;
use App\Http\Requests\Api\V1\Workflow\IndexWorkflowRequest;
use App\Http\Requests\Api\V1\Workflow\ShowWorkflowRequest;
use App\Http\Requests\Api\V1\Workflow\StoreWorkflowRequest;
use App\Http\Requests\Api\V1\Workflow\UpdateWorkflowRequest;
use App\Http\Resources\V1\WorkflowResource;
use App\Models\Workflow;
use App\Models\Workspace;
use App\Services\WorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkflowController extends Controller
{
    public function __construct(private readonly WorkflowService $workflowService) {}

    public function index(IndexWorkflowRequest $request, Workspace $workspace): JsonResponse
    {
        $workflows = $workspace->workflows()
            ->with(['currentVersion', 'tags'])
            ->when($request->query('folder_id'), fn ($q, $folderId) => $q->where('folder_id', $folderId))
            ->when($request->query('is_active') !== null, fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->query('is_favorite') !== null, fn ($q) => $q->where('is_favorite', $request->boolean('is_favorite')))
            ->when($request->query('search'), fn ($q, $search) => $q->where('name', 'ilike', "%{$search}%"))
            ->latest()
            ->paginate((int) $request->query('per_page', 15));

        return $this->paginatedResponse(
            'Workflows retrieved.',
            WorkflowResource::collection($workflows),
        );
    }

    public function store(StoreWorkflowRequest $request, Workspace $workspace): JsonResponse
    {
        $workflow = $this->workflowService->create($workspace, $request->user(), $request->validated());

        return $this->successResponse(
            'Workflow created.',
            new WorkflowResource($workflow),
            201,
        );
    }

    public function show(ShowWorkflowRequest $request, Workspace $workspace, Workflow $workflow): JsonResponse
    {
        return $this->successResponse(
            'Workflow retrieved.',
            new WorkflowResource($workflow->load(['currentVersion', 'tags', 'triggers', 'creator'])),
        );
    }

    public function update(UpdateWorkflowRequest $request, Workspace $workspace, Workflow $workflow): JsonResponse
    {
        if ($workflow->is_locked) {
            return $this->errorResponse('Workflow is locked.', 423);
        }

        $workflow = $this->workflowService->update($workflow, $request->validated());

        return $this->successResponse(
            'Workflow updated.',
            new WorkflowResource($workflow),
        );
    }

    public function destroy(DestroyWorkflowRequest $request, Workspace $workspace, Workflow $workflow): JsonResponse
    {
        if ($workflow->is_locked) {
            return $this->errorResponse('Workflow is locked.', 423);
        }

        $this->workflowService->delete($workflow);

        return $this->successResponse('Workflow deleted.');
    }

    public function activate(Request $request, Workspace $workspace, Workflow $workflow): JsonResponse
    {
        if ($forbidden = $this->requirePermission(Permission::WorkflowActivate)) {
            return $forbidden;
        }

        try {
            $workflow = $this->workflowService->activate($workflow);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }

        return $this->successResponse('Workflow activated.', new WorkflowResource($workflow));
    }

    public function deactivate(Request $request, Workspace $workspace, Workflow $workflow): JsonResponse
    {
        if ($forbidden = $this->requirePermission(Permission::WorkflowActivate)) {
            return $forbidden;
        }

        $workflow = $this->workflowService->deactivate($workflow);

        return $this->successResponse('Workflow deactivated.', new WorkflowResource($workflow));
    }

    public function duplicate(DuplicateWorkflowRequest $request, Workspace $workspace, Workflow $workflow): JsonResponse
    {
        $copy = $this->workflowService->duplicate($workflow, $request->user());

        return $this->successResponse('Workflow duplicated.', new WorkflowResource($copy), 201);
    }
}
