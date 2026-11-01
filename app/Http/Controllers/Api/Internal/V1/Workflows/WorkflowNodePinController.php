<?php

namespace App\Http\Controllers\Api\Internal\V1\Workflows;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Workflows\PinWorkflowNodeRequest;
use App\Http\Resources\Api\Internal\V1\Workflows\WorkflowNodeResource;
use App\Http\Responses\ApiResponse;
use App\Models\Runs\NodeRun;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowNode;
use App\Models\Workspaces\Workspace;

class WorkflowNodePinController extends Controller
{
    public function store(PinWorkflowNodeRequest $request, Workspace $workspace, Workflow $workflow, WorkflowNode $node)
    {
        $this->requirePermission(Permission::WorkflowManage);
        $this->ensureBelongsToWorkspace($workspace, $workflow);
        abort_if($node->workflow_id !== $workflow->id, 404);

        if ($request->filled('node_run_id')) {
            $nodeRun = NodeRun::where('id', $request->validated('node_run_id'))
                ->whereHas('run', fn ($query) => $query->where('workflow_id', $workflow->id))
                ->firstOrFail();

            $data = $nodeRun->output ?? [];
        } else {
            $data = $request->validated('data');
        }

        $node->forceFill([
            'pinned_data' => $data,
            'pinned_at' => now(),
            'pinned_by' => $request->user()->id,
        ])->save();

        $workflow->forceFill(['has_unpublished_changes' => true])->save();

        return ApiResponse::success(['node' => WorkflowNodeResource::make($node)], 'Node data pinned successfully.');
    }

    public function destroy(Workspace $workspace, Workflow $workflow, WorkflowNode $node)
    {
        $this->requirePermission(Permission::WorkflowManage);
        $this->ensureBelongsToWorkspace($workspace, $workflow);
        abort_if($node->workflow_id !== $workflow->id, 404);

        $node->forceFill([
            'pinned_data' => null,
            'pinned_at' => null,
            'pinned_by' => null,
        ])->save();

        $workflow->forceFill(['has_unpublished_changes' => true])->save();

        return ApiResponse::success(['node' => WorkflowNodeResource::make($node)], 'Node unpinned successfully.');
    }
}
