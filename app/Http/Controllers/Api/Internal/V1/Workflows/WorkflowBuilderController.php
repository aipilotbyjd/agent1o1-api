<?php

namespace App\Http\Controllers\Api\Internal\V1\Workflows;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Workflows\ReplaceGraphRequest;
use App\Http\Resources\Api\Internal\V1\Workflows\WorkflowResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;

/**
 * Editor-only concerns (node placement, edges, autosave): separate from
 * plain resource CRUD on `WorkflowController`, per docs/STRUCTURE.md's
 * "Public vs. Internal API" section.
 */
class WorkflowBuilderController extends Controller
{
    /**
     * Replace the draft graph wholesale (an editor autosave, not a partial
     * patch): see `Workflow::replaceGraph()`.
     */
    public function replaceGraph(ReplaceGraphRequest $request, Workspace $workspace, Workflow $workflow)
    {
        $this->requirePermission(Permission::WorkflowBuilderUse);
        $this->ensureBelongsToWorkspace($workspace, $workflow);

        $workflow->replaceGraph($request->validated());

        return ApiResponse::success([
            'workflow' => WorkflowResource::make($workflow->fresh(['nodes', 'edges'])),
        ], 'Draft saved.');
    }
}
