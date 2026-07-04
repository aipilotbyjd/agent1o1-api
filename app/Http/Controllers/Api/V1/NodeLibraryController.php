<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\NodeResource;
use App\Models\ExecutionNode;
use App\Models\Node;
use App\Models\Workspace;
use App\Traits\ResolvesConnectedNodes;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class NodeLibraryController extends Controller
{
    use ResolvesConnectedNodes;

    public function recentlyUsed(Workspace $workspace): JsonResponse
    {
        $used = ExecutionNode::query()
            ->select('execution_nodes.node_type', DB::raw('COUNT(*) as usage_count'))
            ->join('executions', 'executions.id', '=', 'execution_nodes.execution_id')
            ->where('executions.workspace_id', $workspace->id)
            ->groupBy('execution_nodes.node_type')
            ->orderByDesc('usage_count')
            ->limit(6)
            ->pluck('usage_count', 'node_type');

        $isDefault = $used->isEmpty();

        if ($isDefault) {
            $nodes = Node::query()
                ->where('nodes.is_active', true)
                ->join('node_categories', 'node_categories.id', '=', 'nodes.category_id')
                ->orderBy('node_categories.sort_order')
                ->orderBy('nodes.name')
                ->select('nodes.*')
                ->limit(6)
                ->get();
        } else {
            $nodeTypes = $used->keys()->all();
            $nodes = Node::query()
                ->where('is_active', true)
                ->whereIn('type', $nodeTypes)
                ->with('category')
                ->get()
                ->sortByDesc(fn ($n) => $used[$n->type] ?? 0)
                ->values();
        }

        $nodes->load('category');

        $this->loadConnectedTypes($workspace->id);

        return $this->successResponse('Recently used nodes retrieved.', [
            'nodes' => NodeResource::collection($nodes),
            'is_default' => $isDefault,
        ]);
    }

    public function customNodes(Workspace $workspace): JsonResponse
    {
        $nodes = Node::query()
            ->where('is_active', true)
            ->where('is_custom', true)
            ->where('workspace_id', $workspace->id)
            ->with('category')
            ->orderBy('name')
            ->get();

        $this->loadConnectedTypes($workspace->id);

        return $this->successResponse('Custom nodes retrieved.', [
            'nodes' => NodeResource::collection($nodes),
            'total' => $nodes->count(),
        ]);
    }
}
