<?php

namespace App\Http\Controllers\Api\Internal\V1\Billing;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Workspaces\Workspace;
use App\Services\Billing\UsageInsights;
use Illuminate\Http\Request;

/**
 * Where the workspace's credits went: by day, kind of work, workflow, agent
 * and model.
 */
class UsageInsightsController extends Controller
{
    public function __invoke(Request $request, Workspace $workspace, UsageInsights $insights)
    {
        $this->requirePermission(Permission::BillingView);

        return ApiResponse::success($insights->for($workspace, $request->integer('days', 30)));
    }
}
