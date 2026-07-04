<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StickyNote\StoreStickyNoteRequest;
use App\Http\Resources\V1\StickyNoteResource;
use App\Models\StickyNote;
use App\Models\Workflow;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StickyNoteController extends Controller
{
    public function index(Request $request, Workspace $workspace, Workflow $workflow): JsonResponse
    {
        if ($forbidden = $this->requirePermission(Permission::WorkflowView)) {
            return $forbidden;
        }

        return $this->successResponse(
            'Sticky notes retrieved.',
            StickyNoteResource::collection($workflow->stickyNotes),
        );
    }

    public function store(StoreStickyNoteRequest $request, Workspace $workspace, Workflow $workflow): JsonResponse
    {
        $note = StickyNote::create([
            'workflow_id' => $workflow->id,
            'workspace_id' => $workspace->id,
            'created_by' => $request->user()->id,
            ...$request->validated(),
        ]);

        return $this->successResponse('Sticky note created.', new StickyNoteResource($note), 201);
    }

    public function update(StoreStickyNoteRequest $request, Workspace $workspace, Workflow $workflow, StickyNote $stickyNote): JsonResponse
    {
        $stickyNote->update($request->validated());

        return $this->successResponse('Sticky note updated.', new StickyNoteResource($stickyNote->fresh()));
    }

    public function destroy(Request $request, Workspace $workspace, Workflow $workflow, StickyNote $stickyNote): JsonResponse
    {
        if ($forbidden = $this->requirePermission(Permission::WorkflowUpdate)) {
            return $forbidden;
        }

        $stickyNote->delete();

        return $this->successResponse('Sticky note deleted.');
    }
}
