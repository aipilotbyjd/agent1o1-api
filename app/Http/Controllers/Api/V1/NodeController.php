<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Node\IndexNodeRequest;
use App\Http\Requests\Api\V1\Node\ShowNodeRequest;
use App\Http\Resources\V1\NodeResource;
use App\Models\Node;
use App\Traits\ResolvesConnectedNodes;
use Illuminate\Http\JsonResponse;

class NodeController extends Controller
{
    use ResolvesConnectedNodes;

    public function index(IndexNodeRequest $request): JsonResponse
    {
        $workspace = $this->resolveWorkspaceForConnected($request);

        $nodes = Node::query()
            ->with('category')
            ->where('is_active', true)
            ->when($request->validated('category_id'), fn ($q, $id) => $q->where('category_id', $id))
            ->when($request->validated('kind'), fn ($q, $kind) => $q->where('node_kind', $kind))
            ->when($request->validated('search'), fn ($q, $search) => $q->where(fn ($w) => $w
                ->where('name', 'ilike', "%{$search}%")
                ->orWhere('type', 'ilike', "%{$search}%")))
            ->orderBy('name')
            ->get();

        if ($workspace) {
            $this->loadConnectedTypes($workspace->id);
        }

        return $this->successResponse('Nodes retrieved.', NodeResource::collection($nodes));
    }

    public function show(ShowNodeRequest $request, Node $node): JsonResponse
    {
        $workspace = $this->resolveWorkspaceForConnected($request);

        $node->load('category');

        if ($workspace) {
            $this->loadConnectedTypes($workspace->id);
        }

        return $this->successResponse('Node retrieved.', new NodeResource($node));
    }
}
