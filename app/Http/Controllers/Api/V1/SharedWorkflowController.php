<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WorkflowShare;
use App\Models\Workspace;
use App\Services\WorkflowImportExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SharedWorkflowController extends Controller
{
    public function __construct(private readonly WorkflowImportExportService $importExport) {}

    /**
     * Public, read-only view of a shared workflow.
     */
    public function show(Request $request, string $token): JsonResponse
    {
        $share = $this->resolveShare($token);

        if (! $share) {
            return $this->errorResponse('Shared workflow not found or expired.', 404);
        }

        $share->increment('view_count');
        $workflow = $share->workflow->load('currentVersion');

        return $this->successResponse('Shared workflow retrieved.', [
            'name' => $workflow->name,
            'description' => $workflow->description,
            'nodes' => $workflow->currentVersion?->nodes_data ?? [],
            'edges' => $workflow->currentVersion?->edges_data ?? [],
            'allow_clone' => $share->allow_clone,
        ]);
    }

    /**
     * Clone a shared workflow into the authenticated user's current workspace.
     */
    public function clone(Request $request, string $token): JsonResponse
    {
        $share = $this->resolveShare($token);

        if (! $share || ! $share->allow_clone) {
            return $this->errorResponse('This workflow cannot be cloned.', 403);
        }

        $user = $request->user();
        $workspace = $user->current_workspace_id ? Workspace::find($user->current_workspace_id) : null;

        if (! $workspace || ! $this->isMember($workspace, $user->id)) {
            return $this->errorResponse('Select a workspace you belong to before cloning.', 422);
        }

        $payload = $this->importExport->export($share->workflow);
        $workflow = $this->importExport->import($workspace, $user, $payload);

        return $this->successResponse('Workflow cloned into your workspace.', [
            'workflow_id' => $workflow->id,
            'workspace_id' => $workspace->id,
        ], 201);
    }

    private function resolveShare(string $token): ?WorkflowShare
    {
        $share = WorkflowShare::with('workflow.currentVersion')->where('token', $token)->first();

        if (! $share || $share->isExpired() || ! $share->workflow) {
            return null;
        }

        return $share;
    }

    private function isMember(Workspace $workspace, int $userId): bool
    {
        return $workspace->owner_id === $userId
            || $workspace->members()->where('users.id', $userId)->exists();
    }
}
