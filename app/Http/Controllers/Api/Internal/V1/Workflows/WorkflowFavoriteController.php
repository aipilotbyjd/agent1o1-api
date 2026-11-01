<?php

namespace App\Http\Controllers\Api\Internal\V1\Workflows;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Workflows\UpdateWorkflowFavoriteRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;

class WorkflowFavoriteController extends Controller
{
    public function update(UpdateWorkflowFavoriteRequest $request, Workspace $workspace, Workflow $workflow)
    {
        $this->requirePermission(Permission::WorkflowView);
        $this->ensureBelongsToWorkspace($workspace, $workflow);

        if ($request->boolean('is_favorite')) {
            $workflow->favoritedByUsers()->syncWithoutDetaching([$request->user()->id]);
        } else {
            $workflow->favoritedByUsers()->detach($request->user()->id);
        }

        return ApiResponse::success(message: 'Workflow favorite updated.');
    }
}
