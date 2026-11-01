<?php

namespace App\Http\Controllers\Api\Internal\V1\Workflows;

use App\Authorization\WorkspaceContext;
use App\Enums\Billing\Feature;
use App\Enums\Workspaces\AuditAction;
use App\Enums\Workspaces\Permission;
use App\Exceptions\FeatureNotAvailableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Workflows\UpdatePublishApprovalPolicyRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Workspaces\Workspace;
use App\Services\Workflows\PublishRequests;
use App\Services\Workspaces\AuditLogger;
use Illuminate\Http\Request;

/**
 * Whether the workspace reviews workflow changes before they go live:
 * readable by anyone who can see workflows (the editor needs it to offer
 * "Request review" instead of "Publish"), changeable only by an admin, and
 * only switched on while the plan includes approvals.
 */
class PublishApprovalPolicyController extends Controller
{
    public function __construct(
        private readonly PublishRequests $publishRequests,
        private readonly AuditLogger $audit,
    ) {}

    public function show(Request $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::WorkflowView);

        return ApiResponse::success(['policy' => $this->policy($request, $workspace)]);
    }

    public function update(UpdatePublishApprovalPolicyRequest $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::WorkspaceUpdate);

        $enabled = $request->boolean('require_publish_approval');

        if ($enabled && ! $this->planIncludesApprovals($workspace)) {
            throw new FeatureNotAvailableException(Feature::WorkflowApprovals);
        }

        if ($workspace->require_publish_approval !== $enabled) {
            $workspace->forceFill(['require_publish_approval' => $enabled])->save();

            $this->audit->record($workspace->id, AuditAction::PublishApprovalPolicyUpdated, $workspace, [
                'require_publish_approval' => $enabled,
            ]);
        }

        return ApiResponse::success(
            ['policy' => $this->policy($request, $workspace)],
            $enabled ? 'Changes now need a review before they go live.' : 'Changes can be published directly again.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function policy(Request $request, Workspace $workspace): array
    {
        $role = WorkspaceContext::resolveRole($workspace, $request->user());

        return [
            'require_publish_approval' => $workspace->require_publish_approval,
            'plan_includes_approvals' => $this->planIncludesApprovals($workspace),
            'requires_approval' => $workspace->requiresPublishApproval(),
            'can_manage' => $role?->has(Permission::WorkspaceUpdate) ?? false,
            'can_approve' => $role?->has(Permission::WorkflowApprovePublish) ?? false,
            'approver_count' => $this->publishRequests->approvers($workspace)->count(),
        ];
    }

    private function planIncludesApprovals(Workspace $workspace): bool
    {
        return (bool) $workspace->currentPlan()?->hasFeature(Feature::WorkflowApprovals);
    }
}
