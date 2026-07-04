<?php

namespace App\Http\Controllers\Api\V1\WorkflowBuilder;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\WorkflowBuilder\WorkflowBuilderDraftVersionResource;
use App\Http\Resources\V1\WorkflowBuilder\WorkflowBuilderSessionResource;
use App\Models\WorkflowBuilderDraftVersion;
use App\Models\WorkflowBuilderSession;
use App\Models\Workspace;
use App\Services\WorkflowBuilder\DraftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DraftVersionController extends Controller
{
    public function __construct(private readonly DraftService $draftService) {}

    public function index(Request $request, Workspace $workspace, WorkflowBuilderSession $builderSession): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::WorkflowCreate)) {
            return $denied;
        }

        if (! $builderSession->isOwnedBy($request->user()) || $builderSession->workspace_id !== $workspace->id) {
            return $this->errorResponse('Session not found.', 404);
        }

        $versions = $builderSession->draftVersions()
            ->latest()
            ->paginate((int) $request->query('per_page', 20));

        return $this->paginatedResponse('Draft versions retrieved.', WorkflowBuilderDraftVersionResource::collection($versions));
    }

    public function restore(Request $request, Workspace $workspace, WorkflowBuilderSession $builderSession, WorkflowBuilderDraftVersion $version): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::WorkflowCreate)) {
            return $denied;
        }

        if (! $builderSession->isOwnedBy($request->user()) || $builderSession->workspace_id !== $workspace->id) {
            return $this->errorResponse('Session not found.', 404);
        }

        if ($version->session_id !== $builderSession->id) {
            return $this->errorResponse('Version not found.', 404);
        }

        if (! $builderSession->isActive()) {
            return $this->errorResponse('Only active sessions can be restored.', 422);
        }

        $this->draftService->restoreVersion($builderSession, $version);

        return $this->successResponse('Draft restored.', new WorkflowBuilderSessionResource($builderSession->fresh()));
    }
}
