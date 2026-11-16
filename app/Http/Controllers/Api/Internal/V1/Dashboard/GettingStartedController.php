<?php

namespace App\Http\Controllers\Api\Internal\V1\Dashboard;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Workspaces\Workspace;
use App\Services\Dashboard\GettingStarted;
use Illuminate\Http\Request;

class GettingStartedController extends Controller
{
    public function __construct(private readonly GettingStarted $gettingStarted) {}

    /**
     * Hides the getting-started checklist for the caller in this workspace
     * only. Gated like the dashboard that shows it: anyone who can see the
     * checklist can put it away.
     */
    public function dismiss(Request $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::RunView);

        $this->gettingStarted->dismiss($workspace, $request->user());

        return ApiResponse::noContent();
    }
}
