<?php

namespace App\Http\Resources\Api\Internal\V1\Workflows;

use App\Models\Workflows\WorkflowEdge;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WorkflowEdge
 */
class WorkflowEdgeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'from_node_id' => $this->from_node_id,
            'to_node_id' => $this->to_node_id,
            'condition' => $this->condition,
        ];
    }
}
