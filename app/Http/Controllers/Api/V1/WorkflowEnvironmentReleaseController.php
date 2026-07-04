<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Workflow;
use App\Models\WorkflowEnvironmentRelease;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkflowEnvironmentReleaseController extends Controller
{
    public function index(Request $request, Workspace $workspace, Workflow $workflow): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::WorkflowView)) {
            return $denied;
        }

        $releases = WorkflowEnvironmentRelease::query()
            ->where('workflow_id', $workflow->id)
            ->with('environment:id,name,slug')
            ->latest('released_at')
            ->get();

        return $this->successResponse('Releases retrieved.', $releases->toArray());
    }

    /**
     * Promote a workflow version to an environment.
     */
    public function release(Request $request, Workspace $workspace, Workflow $workflow): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::WorkflowActivate)) {
            return $denied;
        }

        $data = $request->validate([
            'environment_id' => ['required', 'uuid', 'exists:workspace_environments,id'],
            'version_id' => ['required', 'uuid'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $version = $workflow->versions()->whereKey($data['version_id'])->first();

        if (! $version) {
            return $this->errorResponse('Version does not belong to this workflow.', 422);
        }

        $release = WorkflowEnvironmentRelease::create([
            'workspace_id' => $workspace->id,
            'workflow_id' => $workflow->id,
            'environment_id' => $data['environment_id'],
            'version_id' => $version->id,
            'released_by' => $request->user()->id,
            'notes' => $data['notes'] ?? null,
            'released_at' => now(),
        ]);

        return $this->successResponse('Workflow released to environment.', $release->toArray(), 201);
    }
}
