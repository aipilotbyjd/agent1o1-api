<?php

namespace App\Http\Resources\Api\Internal\V1\Workflows;

use App\Models\Workflows\WorkflowNode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WorkflowNode
 */
class WorkflowNodeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'type' => $this->type,
            'config' => $this->config,
            'position' => $this->position,
            'pinned_data' => $this->pinned_data,
            'pinned_at' => $this->pinned_at,
            'pinned_by' => $this->pinned_by,
        ];
    }
}
