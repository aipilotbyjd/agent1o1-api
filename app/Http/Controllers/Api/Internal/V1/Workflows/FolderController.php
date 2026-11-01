<?php

namespace App\Http\Controllers\Api\Internal\V1\Workflows;

use App\Enums\Triggers\TriggerTargetType;
use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Workflows\MoveAgentsRequest;
use App\Http\Requests\Api\Internal\V1\Workflows\MoveWorkflowsRequest;
use App\Http\Requests\Api\Internal\V1\Workflows\StoreFolderRequest;
use App\Http\Requests\Api\Internal\V1\Workflows\UpdateFolderRequest;
use App\Http\Resources\Api\Internal\V1\Workflows\FolderResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workflows\Folder;
use App\Models\Workspaces\Workspace;
use Illuminate\Http\Request;

class FolderController extends Controller
{
    public function index(Request $request, Workspace $workspace)
    {
        $type = $request->enum('type', TriggerTargetType::class) ?? TriggerTargetType::Workflow;

        $this->requirePermission($this->viewPermissionFor($type));

        $folders = $workspace->folders()
            ->where('type', $type)
            ->whereNull('parent_id')
            ->with('children')
            ->withCount(['workflows', 'agents'])
            ->orderBy('position')
            ->get();

        return ApiResponse::success(['folders' => FolderResource::collection($folders)]);
    }

    public function store(StoreFolderRequest $request, Workspace $workspace)
    {
        $type = TriggerTargetType::from($request->validated('type'));

        $this->requirePermission($this->managePermissionFor($type));

        $folder = $workspace->folders()->create($request->validated());

        return ApiResponse::created(['folder' => FolderResource::make($folder)], 'Folder created successfully.');
    }

    public function update(UpdateFolderRequest $request, Workspace $workspace, Folder $folder)
    {
        $this->requirePermission($this->managePermissionFor($folder->type));
        $this->ensureBelongsToWorkspace($workspace, $folder);

        $folder->update($request->validated());

        return ApiResponse::success(['folder' => FolderResource::make($folder)], 'Folder updated successfully.');
    }

    public function destroy(Workspace $workspace, Folder $folder)
    {
        $this->requirePermission($this->managePermissionFor($folder->type));
        $this->ensureBelongsToWorkspace($workspace, $folder);

        // Workflows/agents and child folders fall back to no folder (FK is nullOnDelete).
        $folder->delete();

        return ApiResponse::noContent();
    }

    public function moveWorkflows(MoveWorkflowsRequest $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::WorkflowManage);

        $workspace->workflows()
            ->whereIn('id', $request->validated('workflow_ids'))
            ->update(['folder_id' => $request->validated('folder_id')]);

        return ApiResponse::success(message: 'Workflows moved.');
    }

    public function moveAgents(MoveAgentsRequest $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::AgentManage);

        $workspace->agents()
            ->whereIn('id', $request->validated('agent_ids'))
            ->update(['folder_id' => $request->validated('folder_id')]);

        return ApiResponse::success(message: 'Agents moved.');
    }

    private function viewPermissionFor(TriggerTargetType $type): Permission
    {
        return $type === TriggerTargetType::Agent ? Permission::AgentView : Permission::WorkflowView;
    }

    private function managePermissionFor(TriggerTargetType $type): Permission
    {
        return $type === TriggerTargetType::Agent ? Permission::AgentManage : Permission::WorkflowManage;
    }
}
