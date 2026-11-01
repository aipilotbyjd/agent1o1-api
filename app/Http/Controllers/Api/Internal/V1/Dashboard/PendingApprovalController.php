<?php

namespace App\Http\Controllers\Api\Internal\V1\Dashboard;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Internal\V1\Dashboard\PendingApprovalResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workspaces\Workspace;
use App\Services\Dashboard\DashboardMetrics;

class PendingApprovalController extends Controller
{
    public function __construct(private readonly DashboardMetrics $metrics) {}

    /**
     * Every `HumanApproval` node in the workspace still waiting on someone,
     * oldest first: the queue behind the dashboard's `pending_approvals`
     * count, and the only listing of them there is: until now an approval
     * could only be found by already knowing which run it paused.
     *
     * Oldest first rather than newest: this is a work queue, and the run that
     * has been blocked longest is the one to unblock.
     *
     * Gated on `RunView`, not `RunTrigger`: reading the queue is not
     * deciding, and someone who can't approve still needs to see what their
     * team is waiting on.
     */
    public function index(Workspace $workspace)
    {
        $this->requirePermission(Permission::RunView);

        $approvals = $this->metrics->pendingApprovals($workspace)
            ->with(['nodeRun', 'run.workflow'])
            ->oldest('requested_at')
            ->paginate(25);

        return ApiResponse::paginated(PendingApprovalResource::collection($approvals));
    }
}
