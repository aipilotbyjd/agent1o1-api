<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Workflow;
use App\Models\WorkflowApproval;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkflowApprovalController extends Controller
{
    public function index(Request $request, Workspace $workspace, Workflow $workflow): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::WorkflowView)) {
            return $denied;
        }

        return $this->successResponse(
            'Approvals retrieved.',
            $workflow->approvals()->latest()->get()->toArray(),
        );
    }

    public function request(Request $request, Workspace $workspace, Workflow $workflow): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::WorkflowUpdate)) {
            return $denied;
        }

        $approval = $workflow->approvals()->create([
            'workspace_id' => $workspace->id,
            'version_id' => $workflow->current_version_id,
            'requested_by' => $request->user()->id,
            'status' => 'pending',
            'notes' => $request->input('notes'),
        ]);

        return $this->successResponse('Approval requested.', $approval->toArray(), 201);
    }

    public function approve(Request $request, Workspace $workspace, Workflow $workflow, WorkflowApproval $approval): JsonResponse
    {
        return $this->review($request, $approval, 'approved');
    }

    public function reject(Request $request, Workspace $workspace, Workflow $workflow, WorkflowApproval $approval): JsonResponse
    {
        return $this->review($request, $approval, 'rejected');
    }

    private function review(Request $request, WorkflowApproval $approval, string $status): JsonResponse
    {
        // Approvals are governance gates — only admins/owners may review.
        if ($denied = $this->requirePermission(Permission::WorkflowActivate)) {
            return $denied;
        }

        if (! $approval->isPending()) {
            return $this->errorResponse('This approval has already been reviewed.', 422);
        }

        $approval->update([
            'status' => $status,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'notes' => $request->input('notes', $approval->notes),
        ]);

        return $this->successResponse("Approval {$status}.", $approval->toArray());
    }
}
