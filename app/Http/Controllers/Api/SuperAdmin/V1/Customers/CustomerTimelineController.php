<?php

namespace App\Http\Controllers\Api\SuperAdmin\V1\Customers;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\SuperAdmin\Audit\SuperAdminAuditLogger;
use App\Services\SuperAdmin\Customers\CustomerTimeline;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class CustomerTimelineController extends Controller
{
    public function __construct(
        private readonly CustomerTimeline $timeline,
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
        $request->validate([
            'before' => ['nullable', 'date'],
            'types' => ['nullable', 'array'],
            'types.*' => [Rule::in(CustomerTimeline::TYPES)],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.CustomerTimeline::MAX_LIMIT],
        ]);

        $admin = $request->user('super-admin');

        $page = $this->timeline->page(
            $admin,
            $subject,
            $request->filled('before') ? Carbon::parse($request->string('before')->toString()) : null,
            (array) $request->input('types', []),
            $request->integer('limit', CustomerTimeline::DEFAULT_LIMIT),
        );

        $this->audit->record($admin, ($subject instanceof User ? 'users' : 'workspaces').'.timeline_viewed', $subject);

        return ApiResponse::success($page);
    }
}
