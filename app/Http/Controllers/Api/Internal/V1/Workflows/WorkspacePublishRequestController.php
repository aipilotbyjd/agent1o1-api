<?php

namespace App\Http\Controllers\Api\Internal\V1\Workflows;

use App\Enums\Workflows\PublishRequestStatus;
use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Internal\V1\Workflows\WorkflowPublishRequestResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workflows\WorkflowPublishRequest;
use App\Models\Workspaces\Workspace;

/**
 * Every workflow change waiting for review in the workspace, oldest first
 * (the one blocked longest is the one to look at) for the approvals inbox.
 */
class WorkspacePublishRequestController extends Controller
{
    public function index(Workspace $workspace)
    {
        $this->requirePermission(Permission::WorkflowView);

        $pending = WorkflowPublishRequest::query()
            ->where('workspace_id', $workspace->id)
            ->where('status', PublishRequestStatus::Pending)
            ->whereHas('workflow')
            ->select(['id', 'workflow_id', 'workspace_id', 'requested_by', 'status', 'notes', 'base_version_id', 'reviewed_at', 'review_notes', 'created_at'])
            ->with(['workflow:id,name', 'requester:id,name', 'baseVersion:id,version'])
            ->oldest()
            ->get();

        return ApiResponse::success(['requests' => WorkflowPublishRequestResource::collection($pending)]);
    }
}
