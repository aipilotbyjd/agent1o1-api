<?php

namespace App\Http\Controllers\Api\Internal\V1\Workflows;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Workflows\RestoreWorkflowBuilderDraftVersionRequest;
use App\Http\Resources\Api\Internal\V1\Workflows\WorkflowBuilderDraftVersionResource;
use App\Http\Resources\Api\Internal\V1\Workflows\WorkflowBuilderSessionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workflows\Builder\WorkflowBuilderDraftVersion;
use App\Models\Workflows\Builder\WorkflowBuilderSession;
use App\Models\Workspaces\Workspace;
use Illuminate\Http\Request;

/**
 * Undo history for a builder session: every edit, by the assistant or on
 * the canvas, is a labelled snapshot (`WorkflowBuilderSession::applyGraph()`),
 * and restoring one is itself a new snapshot.
 */
class WorkflowBuilderDraftVersionController extends Controller
{
    public function index(Request $request, Workspace $workspace, WorkflowBuilderSession $session)
    {
        $this->requirePermission(Permission::WorkflowBuilderUse);
        $this->ensureBelongsToWorkspace($workspace, $session);

        $versions = $session->draftVersions()
            ->latest()
            ->latest('id')
            ->paginate(min((int) $request->query('per_page', 20), 100));

        return ApiResponse::paginated(WorkflowBuilderDraftVersionResource::collection($versions));
    }

    public function restore(RestoreWorkflowBuilderDraftVersionRequest $request, Workspace $workspace, WorkflowBuilderSession $session, WorkflowBuilderDraftVersion $version)
    {
        $this->requirePermission(Permission::WorkflowBuilderUse);
        $this->ensureBelongsToWorkspace($workspace, $session);
        abort_if($version->session_id !== $session->id, 404);
        $session->assertEditable();

        $expectedLockVersion = $request->validated('draft_lock_version');

        $session->restoreVersion($version, $request->user(), $expectedLockVersion !== null ? (int) $expectedLockVersion : null);

        return ApiResponse::success([
            'session' => WorkflowBuilderSessionResource::make($session),
        ], 'Draft restored.');
    }
}
