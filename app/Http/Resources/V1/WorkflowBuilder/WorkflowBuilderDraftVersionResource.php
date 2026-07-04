<?php

namespace App\Http\Resources\V1\WorkflowBuilder;

use App\Models\WorkflowBuilderDraftVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WorkflowBuilderDraftVersion
 */
class WorkflowBuilderDraftVersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'node_count' => count($this->nodes_snapshot ?? []),
            'edge_count' => count($this->edges_snapshot ?? []),
            'nodes_snapshot' => $this->when($request->routeIs('*.versions.show'), $this->nodes_snapshot),
            'edges_snapshot' => $this->when($request->routeIs('*.versions.show'), $this->edges_snapshot),
            'triggered_by' => $this->triggered_by,
            'created_at' => $this->created_at,
        ];
    }
}
