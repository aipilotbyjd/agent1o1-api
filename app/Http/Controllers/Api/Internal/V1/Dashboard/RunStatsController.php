<?php

namespace App\Http\Controllers\Api\Internal\V1\Dashboard;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Dashboard\RunStatsRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Workspaces\Workspace;
use App\Services\Dashboard\DashboardMetrics;

class RunStatsController extends Controller
{
    public function __construct(private readonly DashboardMetrics $metrics) {}

    /**
     * Execution health over the window: the totals for a header row, a
     * zero-filled daily series for the chart, and the busiest workflows.
     *
     * Passing `workflow_id` narrows `totals` and `series` to that one
     * workflow: the same chart on a workflow's own page. `top_workflows` is
     * left workspace-wide either way: a one-row leaderboard is not a
     * leaderboard, and the client is already showing which workflow it asked
     * about.
     *
     * An unknown or foreign `workflow_id` reports zeros rather than 404ing:
     * the underlying query is workspace-scoped, so there is nothing to leak
     * and nothing to find.
     */
    public function index(RunStatsRequest $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::RunView);

        $window = $request->window();
        $workflowId = $request->workflowId();

        return ApiResponse::success([
            'window' => $window->toArray(),
            'workflow_id' => $workflowId,
            'totals' => $this->metrics->runTotals($workspace, $window, $workflowId),
            'in_flight' => $this->metrics->inFlightByStatus($workspace),
            'series' => $this->metrics->runSeries($workspace, $window, $workflowId),
            'top_workflows' => $this->metrics->topWorkflowsByRuns($workspace, $window),
        ]);
    }
}
