<?php

namespace App\Http\Controllers\Api\SuperAdmin\V1\Models;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Ai\Operations\ByokOverview;
use App\Services\Ai\Operations\ModelHealthReport;
use App\Services\Ai\Operations\ModelMargins;
use App\Services\Ai\Operations\ModelProviderOverview;
use App\Services\Ai\Operations\ModelUsageReport;
use Illuminate\Http\Request;

/**
 * The read-only views of the model setup: what is wrong, what is connected,
 * what workspaces bring, what is used and what it earns.
 */
class SuperAdminModelInsightController extends Controller
{
    public function health(ModelHealthReport $report)
    {
        return ApiResponse::success($report->run());
    }

    public function providers(ModelProviderOverview $providers)
    {
        return ApiResponse::success($providers->list());
    }

    public function byok(ByokOverview $overview)
    {
        return ApiResponse::success($overview->report());
    }

    public function usage(Request $request, ModelUsageReport $report)
    {
        $request->validate([
            'days' => ['nullable', 'integer', 'between:1,'.ModelUsageReport::MAX_DAYS],
            'workspace_id' => ['nullable', 'uuid'],
        ]);

        return ApiResponse::success($report->report($request->integer('days', 7), $request->input('workspace_id')));
    }

    /**
     * The workspaces that cost you the most.
     */
    public function topSpenders(Request $request, ModelUsageReport $report)
    {
        $request->validate(['days' => ['nullable', 'integer', 'between:1,'.ModelUsageReport::MAX_DAYS]]);

        return ApiResponse::success($report->topWorkspaces($request->integer('days', 7)));
    }

    /**
     * Calls and cost per day, for a trend line.
     */
    public function dailyUsage(Request $request, ModelUsageReport $report)
    {
        $request->validate(['days' => ['nullable', 'integer', 'between:1,'.ModelUsageReport::MAX_DAYS]]);

        return ApiResponse::success($report->daily($request->integer('days', 14)));
    }

    public function margins(Request $request, ModelMargins $margins)
    {
        $request->validate(['lifetime_months' => ['nullable', 'integer', 'between:6,120']]);

        return ApiResponse::success($margins->report($request->integer('lifetime_months', 36)));
    }
}
