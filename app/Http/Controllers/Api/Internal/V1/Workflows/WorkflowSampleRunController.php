<?php

namespace App\Http\Controllers\Api\Internal\V1\Workflows;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Workflows\StartSampleRunRequest;
use App\Http\Resources\Api\Internal\V1\Runs\RunResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Services\Workflows\SampleRuns;
use Illuminate\Http\Request;

/**
 * The "Run it" step: a run on example answers where app steps are shown
 * rather than executed. See `SampleRuns`.
 */
class WorkflowSampleRunController extends Controller
{
    public function __construct(private readonly SampleRuns $sampleRuns) {}

    public function show(Request $request, Workspace $workspace, Workflow $workflow)
    {
        $this->requirePermission(Permission::WorkflowView);
        $this->ensureBelongsToWorkspace($workspace, $workflow);

        return ApiResponse::success($this->sampleRuns->plan($workflow, $request->user()));
    }

    /**
     * A workflow that has never been published is published by its first
     * sample run, so starting one then also takes the right to publish.
     */
    public function store(StartSampleRunRequest $request, Workspace $workspace, Workflow $workflow)
    {
        $this->requirePermission(Permission::RunTrigger);
        $this->ensureBelongsToWorkspace($workspace, $workflow);

        if ($workflow->current_version_id === null) {
            $this->requirePermission(Permission::WorkflowPublish);
        }

        $run = $this->sampleRuns->start($workflow, $request->user(), $request->validated('input') ?? []);

        return ApiResponse::success(['run' => RunResource::make($run)], 'Sample run started.', 202);
    }
}
