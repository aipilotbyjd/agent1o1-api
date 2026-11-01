<?php

namespace App\Http\Controllers\Api\SuperAdmin\V1\Customers;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\SuperAdmin\Audit\SuperAdminAuditLogger;
use App\Services\SuperAdmin\Customers\CustomerOverview;
use Illuminate\Http\Request;

class CustomerOverviewController extends Controller
{
    public function __construct(
        private readonly CustomerOverview $overview,
        private readonly SuperAdminAuditLogger $audit,
    ) {}

    public function user(Request $request, User $user)
    {
        return $this->respond($request, $user);
    }

    public function workspace(Request $request, Workspace $workspace)
    {
        return $this->respond($request, $workspace);
    }

    private function respond(Request $request, User|Workspace $subject)
    {
        $admin = $request->user('super-admin');
        $data = $this->overview->for($admin, $subject);

        $this->audit->record($admin, ($subject instanceof User ? 'users' : 'workspaces').'.overview_viewed', $subject);

        return ApiResponse::success($data);
    }
}
