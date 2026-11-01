<?php

namespace App\Http\Controllers\Api\Internal\V1\Runs;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Runs\DecideApprovalRequest;
use App\Http\Resources\Api\Internal\V1\Runs\WorkflowApprovalResource;
use App\Http\Responses\ApiResponse;
use App\Models\Runs\Run;
use App\Models\Workflows\WorkflowApproval;
use App\Models\Workspaces\Workspace;
use App\Services\Workflows\WorkflowRunner;

class WorkflowApprovalController extends Controller
{
    public function __construct(private readonly WorkflowRunner $runner) {}

    public function decide(DecideApprovalRequest $request, Workspace $workspace, Run $run, WorkflowApproval $approval)
    {
        $this->requirePermission(Permission::RunTrigger);
        $this->ensureBelongsToWorkspace($workspace, $run);
        abort_if($approval->run_id !== $run->id, 404);

        $this->runner->resolveApproval(
            $approval,
            $request->validated('decision') === 'approve',
            $request->user(),
            $request->validated('message'),
            $request->approvalInputs(),
        );

        return ApiResponse::success(['approval' => WorkflowApprovalResource::make($approval->fresh())]);
    }
}
