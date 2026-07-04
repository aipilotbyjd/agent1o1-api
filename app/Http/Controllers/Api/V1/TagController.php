<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Tag\StoreTagRequest;
use App\Http\Resources\V1\TagResource;
use App\Models\Tag;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TagController extends Controller
{
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        if ($forbidden = $this->requirePermission(Permission::WorkflowView)) {
            return $forbidden;
        }

        $tags = $workspace->tags()
            ->withCount('workflows')
            ->orderBy('name')
            ->get();

        return $this->successResponse('Tags retrieved.', TagResource::collection($tags));
    }

    public function store(StoreTagRequest $request, Workspace $workspace): JsonResponse
    {
        $data = $request->validated();

        if ($workspace->tags()->where('name', $data['name'])->exists()) {
            return $this->errorResponse('A tag with this name already exists.', 422);
        }

        $tag = Tag::create([
            'workspace_id' => $workspace->id,
            ...$data,
        ]);

        return $this->successResponse('Tag created.', new TagResource($tag), 201);
    }

    public function update(StoreTagRequest $request, Workspace $workspace, Tag $tag): JsonResponse
    {
        $tag->update($request->validated());

        return $this->successResponse('Tag updated.', new TagResource($tag->fresh()));
    }

    public function destroy(Request $request, Workspace $workspace, Tag $tag): JsonResponse
    {
        if ($forbidden = $this->requirePermission(Permission::WorkflowUpdate)) {
            return $forbidden;
        }

        $tag->delete();

        return $this->successResponse('Tag deleted.');
    }
}
