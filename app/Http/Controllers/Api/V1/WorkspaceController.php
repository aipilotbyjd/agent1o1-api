<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Workspace\StoreWorkspaceRequest;
use App\Http\Requests\Api\V1\Workspace\UpdateWorkspaceRequest;
use App\Http\Resources\V1\WorkspaceResource;
use App\Models\Workspace;
use App\Services\WorkspaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    public function __construct(private WorkspaceService $workspaceService) {}

    public function index(Request $request): JsonResponse
    {
        $workspaces = $request->user()
            ->workspaces()
            ->with('owner')
            ->withCount(['members', 'workflows', 'agents'])
            ->paginate(15);

        return $this->paginatedResponse(
            'Workspaces retrieved.',
            WorkspaceResource::collection($workspaces),
        );
    }

    public function store(StoreWorkspaceRequest $request): JsonResponse
    {
        $workspace = $this->workspaceService->create($request->user(), $request->validated());

        return $this->successResponse(
            'Workspace created.',
            new WorkspaceResource($workspace->load('owner')),
            201,
        );
    }

    public function show(Request $request, Workspace $workspace): JsonResponse
    {
        return $this->successResponse(
            'Workspace retrieved.',
            new WorkspaceResource($workspace->load('owner')),
        );
    }

    public function update(UpdateWorkspaceRequest $request, Workspace $workspace): JsonResponse
    {
        $workspace = $this->workspaceService->update($workspace, $request->validated());

        return $this->successResponse(
            'Workspace updated.',
            new WorkspaceResource($workspace->load('owner')),
        );
    }

    public function destroy(Request $request, Workspace $workspace): JsonResponse
    {
        if ($forbidden = $this->requirePermission(Permission::WorkspaceDelete)) {
            return $forbidden;
        }

        $this->workspaceService->delete($workspace, $request->user());

        return $this->successResponse('Workspace deleted.');
    }
}
