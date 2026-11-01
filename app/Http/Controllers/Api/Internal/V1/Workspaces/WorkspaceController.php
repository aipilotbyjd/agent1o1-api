<?php

namespace App\Http\Controllers\Api\Internal\V1\Workspaces;

use App\Authorization\WorkspaceContext;
use App\Enums\Workspaces\Permission;
use App\Enums\Workspaces\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Workspaces\StoreWorkspaceRequest;
use App\Http\Requests\Api\Internal\V1\Workspaces\UpdateWorkspaceRequest;
use App\Http\Resources\Api\Internal\V1\Workspaces\WorkspaceResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    public function __construct(
        private readonly WorkspaceService $workspaces,
    ) {}

    public function index(Request $request)
    {
        $workspaces = $request->user()->workspaces()->withCount(['workflows', 'agents'])->get();

        return ApiResponse::success(['workspaces' => WorkspaceResource::collection($workspaces)]);
    }

    public function store(StoreWorkspaceRequest $request)
    {
        $workspace = $this->workspaces->create($request->user(), $request->validated());
        $workspace->loadCount(['workflows', 'agents']);

        return ApiResponse::created(['workspace' => WorkspaceResource::make($workspace)->withRole(Role::Owner)], 'Workspace created successfully.');
    }

    public function show(Workspace $workspace, WorkspaceContext $context)
    {
        $this->requirePermission(Permission::WorkspaceView);

        $workspace->loadCount(['workflows', 'agents']);

        return ApiResponse::success(['workspace' => WorkspaceResource::make($workspace)->withRole($context->role)]);
    }

    public function update(UpdateWorkspaceRequest $request, Workspace $workspace, WorkspaceContext $context)
    {
        $this->requirePermission(Permission::WorkspaceUpdate);

        $workspace = $this->workspaces->update($workspace, $request->validated());
        $workspace->loadCount(['workflows', 'agents']);

        return ApiResponse::success(['workspace' => WorkspaceResource::make($workspace)->withRole($context->role)], 'Workspace updated successfully.');
    }

    public function destroy(Workspace $workspace)
    {
        $this->requirePermission(Permission::WorkspaceDelete);

        $this->workspaces->delete($workspace);

        return ApiResponse::noContent();
    }
}
