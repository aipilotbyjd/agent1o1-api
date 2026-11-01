<?php

namespace App\Http\Controllers\Api\Internal\V1\Workflows;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Workflows\PublishWorkflowRequest;
use App\Http\Resources\Api\Internal\V1\Workflows\WorkflowResource;
use App\Http\Resources\Api\Internal\V1\Workflows\WorkflowVersionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowVersion;
use App\Models\Workspaces\Workspace;

class WorkflowVersionController extends Controller
{
    public function index(Workspace $workspace, Workflow $workflow)
    {
        $this->requirePermission(Permission::WorkflowVersion);
        $this->ensureBelongsToWorkspace($workspace, $workflow);

        $versions = $workflow->versions()
            ->select(['id', 'workflow_id', 'version', 'notes', 'published_by', 'created_at'])
            ->with('publisher:id,name')
            ->withCount('runs')
            ->latest('version')
            ->get();

        return ApiResponse::success([
            'versions' => WorkflowVersionResource::collection($versions),
        ]);
    }

    public function show(Workspace $workspace, Workflow $workflow, WorkflowVersion $version)
    {
        $this->requirePermission(Permission::WorkflowVersion);
        $this->ensureBelongsToWorkspace($workspace, $workflow);
        abort_if($version->workflow_id !== $workflow->id, 404);

        return ApiResponse::success([
            'version' => WorkflowVersionResource::make($version->load('publisher:id,name')->loadCount('runs')),
        ]);
    }

    /**
     * Validate the current draft (`GraphValidator`, the full sequence) and,
     * if it passes, snapshot it as a new immutable version: see
     * `Workflow::publishVersion()`.
     */
    public function store(PublishWorkflowRequest $request, Workspace $workspace, Workflow $workflow)
    {
        $this->requirePermission(Permission::WorkflowPublish);
        $this->ensureBelongsToWorkspace($workspace, $workflow);

        $version = $workflow->publishVersion($request->validated('notes'), $request->user());

        return ApiResponse::created([
            'version' => WorkflowVersionResource::make($version),
            'workflow' => WorkflowResource::make($workflow->fresh()),
        ], 'Workflow published successfully.');
    }

    /**
     * Copy a published version back into the draft: see
     * `Workflow::restoreVersion()`. Nothing goes live until it is published.
     */
    public function restore(Workspace $workspace, Workflow $workflow, WorkflowVersion $version)
    {
        $this->requirePermission(Permission::WorkflowBuilderUse);
        $this->ensureBelongsToWorkspace($workspace, $workflow);
        abort_if($version->workflow_id !== $workflow->id, 404);

        $workflow->restoreVersion($version);

        return ApiResponse::success([
            'workflow' => WorkflowResource::make($workflow->fresh(['nodes', 'edges'])),
        ], "Version {$version->version} restored to the draft.");
    }
}
