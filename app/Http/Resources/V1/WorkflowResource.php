<?php

namespace App\Http\Resources\V1;

use App\Models\Workflow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Workflow
 */
class WorkflowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'icon' => $this->icon,
            'color' => $this->color,
            'folder_id' => $this->folder_id,
            'is_active' => $this->is_active,
            'is_locked' => $this->is_locked,
            'is_favorite' => (bool) $this->is_favorite,
            'execution_count' => $this->execution_count,
            'last_executed_at' => $this->last_executed_at,
            'success_rate' => $this->success_rate,
            'max_concurrent_executions' => $this->max_concurrent_executions,
            // Flattened graph from the current version so list cards can show a
            // node count / app tags without digging into `current_version`.
            'nodes' => $this->whenLoaded('currentVersion', fn () => $this->currentVersion?->nodes_data ?? [], []),
            'node_count' => $this->whenLoaded('currentVersion', fn () => count($this->currentVersion?->nodes_data ?? []), 0),
            'current_version' => new WorkflowVersionResource($this->whenLoaded('currentVersion')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'triggers' => TriggerResource::collection($this->whenLoaded('triggers')),
            'creator' => new UserResource($this->whenLoaded('creator')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
