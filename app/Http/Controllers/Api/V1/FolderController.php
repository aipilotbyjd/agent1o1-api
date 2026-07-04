<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Folder\DestroyFolderRequest;
use App\Http\Requests\Api\V1\Folder\StoreFolderRequest;
use App\Http\Resources\V1\FolderResource;
use App\Models\Folder;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FolderController extends Controller
{
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        if ($forbidden = $this->requirePermission(Permission::WorkflowView)) {
            return $forbidden;
        }

        $folders = $workspace->folders()
            ->whereNull('parent_id')
            ->with('children')
            ->withCount('workflows')
            ->orderBy('position')
            ->get();

        return $this->successResponse(
            'Folders retrieved.',
            FolderResource::collection($folders),
        );
    }

    public function store(StoreFolderRequest $request, Workspace $workspace): JsonResponse
    {
        if ($forbidden = $this->requirePermission(Permission::WorkflowCreate)) {
            return $forbidden;
        }

        $folder = Folder::create([
            'workspace_id' => $workspace->id,
            ...$request->validated(),
        ]);

        return $this->successResponse('Folder created.', new FolderResource($folder), 201);
    }

    public function update(StoreFolderRequest $request, Workspace $workspace, Folder $folder): JsonResponse
    {
        if ($forbidden = $this->requirePermission(Permission::WorkflowUpdate)) {
            return $forbidden;
        }

        $folder->update($request->validated());

        return $this->successResponse('Folder updated.', new FolderResource($folder->fresh()));
    }

    public function destroy(DestroyFolderRequest $request, Workspace $workspace, Folder $folder): JsonResponse
    {
        // Workflows in this folder fall back to no folder (FK is nullOnDelete)
        $folder->delete();

        return $this->successResponse('Folder deleted.');
    }
}
