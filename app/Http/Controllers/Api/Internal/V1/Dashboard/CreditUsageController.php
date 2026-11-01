<?php

namespace App\Http\Controllers\Api\Internal\V1\Dashboard;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Dashboard\DashboardWindowRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Workspaces\Workspace;
use App\Services\Dashboard\DashboardMetrics;

class CreditUsageController extends Controller
{
    public function __construct(private readonly DashboardMetrics $metrics) {}

    /**
     * Where the credits went: total burn over the window, a zero-filled daily
     * series, the split by what caused each charge, and the workflows and
     * agents that cost the most.
     *
     * This reads the ledger, so it answers "what did we spend between these
     * dates". `GET /billing` answers the different question of "what is left
     * this period": the two only line up when the window happens to be the
     * current billing month.
     *
     * `topup_credits` is the slice of the total that came out of the
     * non-expiring credit-pack pool rather than the plan allowance, so a
     * workspace can see how fast it is eating what it bought.
     */
    public function index(DashboardWindowRequest $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::BillingView);

        $window = $request->window();
        $totals = $this->metrics->creditTotals($workspace, $window);

        return ApiResponse::success([
            'window' => $window->toArray(),
            'total_credits' => $totals['total'],
            'topup_credits' => $totals['topup'],
            'by_source_type' => $totals['by_source_type'],
            'series' => $this->metrics->creditSeries($workspace, $window),
            'top_workflows' => $this->metrics->topWorkflowsByCredits($workspace, $window),
            'top_agents' => $this->metrics->topAgentsByCredits($workspace, $window),
        ]);
    }
}
