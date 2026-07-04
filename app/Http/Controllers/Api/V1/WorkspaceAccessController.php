<?php

namespace App\Http\Controllers\Api\V1;

use App\Authorization\WorkspaceContext;
use App\Enums\Limit;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\PlanResource;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceAccessController extends Controller
{
    public function __construct(private WorkspaceContext $context) {}

    /**
     * Return the caller's effective role, permissions (post-plan filtering),
     * plan info, and current usage so the frontend never re-implements the
     * permission/plan matrix.
     */
    public function __invoke(Request $request, Workspace $workspace): JsonResponse
    {
        if ($forbidden = $this->requirePermission(Permission::WorkspaceView)) {
            return $forbidden;
        }

        $role = $this->context->role;
        $plan = $this->context->plan;

        // Compute effective permissions — only those allowed() returns true for
        $effectivePermissions = [];
        if ($role !== null) {
            foreach ($role->permissions() as $permission) {
                if ($this->context->allows($permission)) {
                    $effectivePermissions[] = $permission->value;
                }
            }
        }

        // Usage snapshot
        $period = $workspace->currentPeriod;
        $memberCount = $workspace->members()->count();

        $planLimit = $plan?->getLimit(Limit::Seats) ?? -1;

        return $this->successResponse('Access retrieved.', [
            'role' => $request->attributes->get('workspace_role'),
            'permissions' => $effectivePermissions,
            'plan' => $plan ? new PlanResource($plan) : null,
            'usage' => [
                'members' => [
                    'current' => $memberCount,
                    'max' => $planLimit === -1 ? null : $planLimit,
                    'unlimited' => $planLimit === -1,
                ],
                'credits' => $period ? [
                    'available' => $period->isUnlimited() ? null : $period->creditsRemaining(),
                    'used' => $period->credits_used,
                    'limit' => $period->isUnlimited() ? null : $period->credits_limit,
                    'unlimited' => $period->isUnlimited(),
                    'period_end' => $period->period_end,
                ] : null,
            ],
        ]);
    }
}
