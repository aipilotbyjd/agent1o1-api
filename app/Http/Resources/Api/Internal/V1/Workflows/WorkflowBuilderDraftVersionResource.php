<?php

namespace App\Http\Resources\Api\Internal\V1\Workflows;

use App\Models\Workflows\Builder\WorkflowBuilderDraftVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WorkflowBuilderDraftVersion
 */
class WorkflowBuilderDraftVersionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'session_id' => $this->session_id,
            'label' => $this->label,
            'triggered_by' => $this->triggered_by,
            'node_count' => count($this->graph_snapshot['nodes'] ?? []),
            'edge_count' => count($this->graph_snapshot['edges'] ?? []),
            'graph_snapshot' => $this->when($request->boolean('include_graph'), $this->graph_snapshot),
            'created_at' => $this->created_at,
        ];
    }
}
