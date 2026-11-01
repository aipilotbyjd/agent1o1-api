<?php

namespace App\Http\Controllers\Api\Internal\V1\Dashboard;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Dashboard\DashboardWindowRequest;
use App\Http\Resources\Api\Internal\V1\Runs\RunResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workspaces\Workspace;
use App\Services\Dashboard\DashboardMetrics;
use App\Services\Dashboard\DashboardWindow;
use App\Services\Workflows\NodeTester;

class DashboardController extends Controller
{
    /**
     * How many recent runs the home screen's activity list carries. Small on
     * purpose: this endpoint exists so the dashboard is one request, and a
     * user who wants the full history has `GET /runs`.
     */
    private const int RECENT_RUNS = 5;

    public function __construct(private readonly DashboardMetrics $metrics) {}

    /**
     * The whole home screen in one read, the way `BillingController::overview`
     * is the whole billing screen: a dashboard that fans out to six endpoints
     * renders in six waterfalls and shows six separate spinners.
     *
     * Everything under `runs` and `credits.window_*` is scoped to the `days`
     * window; `in_flight`, `pending_approvals` and `counts` are current-state
     * and deliberately are not: a run stuck since last week and an approval
     * nobody has actioned are the two things a dashboard exists to surface,
     * and a window would hide exactly the oldest, worst cases.
     *
     * `credits` is null when the caller can't view billing. Every workspace
     * role can today, so in practice it is always present; it degrades rather
     * than 403s so that narrowing `BillingView` later costs the dashboard a
     * tile instead of the whole page.
     */
    public function overview(DashboardWindowRequest $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::RunView);

        $window = $request->window();

        return ApiResponse::success([
            'window' => $window->toArray(),
            'runs' => $this->metrics->runTotals($workspace, $window),
            'in_flight' => $this->metrics->inFlightByStatus($workspace),
            'pending_approvals' => $this->metrics->pendingApprovals($workspace)->count(),
            'pending_agent_actions' => $this->metrics->pendingAgentActions($workspace)->count(),
            'credits' => $this->credits($request, $workspace, $window),
            'counts' => $this->counts($workspace),
            'recent_runs' => RunResource::collection(
                $workspace->runs()
                    ->whereNull('parent_run_id')
                    ->where('trigger_type', '!=', NodeTester::TRIGGER_TYPE)
                    ->with('nodeRuns.creditTransaction')
                    ->latest()
                    ->limit(self::RECENT_RUNS)
                    ->get(),
            ),
        ]);
    }

    /**
     * Balance and burn side by side: `available`/`used_this_period` come from
     * the billing period (the numbers a plan is actually enforced against),
     * `window_credits` from the ledger over the dashboard's own window. The
     * two disagree whenever the window isn't the current month, which is why
     * both are named for where they come from.
     *
     * @return array<string, mixed>|null
     */
    private function credits(DashboardWindowRequest $request, Workspace $workspace, DashboardWindow $window): ?array
    {
        if ($request->user()?->can(Permission::BillingView->value) !== true) {
            return null;
        }

        $period = $workspace->currentUsagePeriod();
        $totals = $this->metrics->creditTotals($workspace, $window);

        return [
            'available' => $workspace->availableCredits(),
            'used_this_period' => $period->credits_used,
            'limit_this_period' => $period->credits_limit,
            'period_starts_at' => $period->starts_at,
            'period_ends_at' => $period->ends_at,
            'window_credits' => $totals['total'],
        ];
    }

    /**
     * The "you have N of these" tiles. Workflows use `visible()` so the
     * hidden child workflows Loop Mode generates don't inflate a count the
     * user is also shown a plan limit against.
     *
     * @return array<string, int>
     */
    private function counts(Workspace $workspace): array
    {
        return [
            'workflows' => $workspace->workflows()->visible()->count(),
            'agents' => $workspace->agents()->count(),
            'active_triggers' => $workspace->triggers()->where('is_active', true)->count(),
            'members' => $workspace->members()->count(),
        ];
    }
}
