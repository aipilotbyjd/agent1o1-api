<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Node\ShowNodeCategoryRequest;
use App\Http\Resources\V1\NodeCategoryResource;
use App\Models\NodeCategory;
use App\Traits\ResolvesConnectedNodes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NodeCategoryController extends Controller
{
    use ResolvesConnectedNodes;

    public function index(Request $request): JsonResponse
    {
        $categories = NodeCategory::query()
            ->withCount('nodes')
            ->when($request->boolean('include_nodes'), fn ($q) => $q->with([
                'nodes' => fn ($n) => $n->where('is_active', true)->orderBy('name'),
            ]))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return $this->successResponse('Node categories retrieved.', NodeCategoryResource::collection($categories));
    }

    public function show(ShowNodeCategoryRequest $request, NodeCategory $nodeCategory): JsonResponse
    {
        $workspace = $this->resolveWorkspaceForConnected($request);

        $nodeCategory->load(['nodes' => fn ($q) => $q->where('is_active', true)->orderBy('name')]);
        $nodeCategory->loadCount(['nodes' => fn ($q) => $q->where('is_active', true)]);

        if ($workspace) {
            $this->loadConnectedTypes($workspace->id);
        }

        return $this->successResponse('Node category retrieved.', new NodeCategoryResource($nodeCategory));
    }
}
