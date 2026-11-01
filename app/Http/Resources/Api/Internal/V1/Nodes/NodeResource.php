<?php

namespace App\Http\Resources\Api\Internal\V1\Nodes;

use App\Models\Nodes\CustomNode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A workspace-authored node (`CustomNode`). Built-in nodes never reach this
 * class: `NodeController::index()` renders `NodeRegistry::catalog()`'s
 * plain arrays directly, since they have no Eloquent row to wrap.
 *
 * @mixin CustomNode
 */
class NodeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'category' => $this->category?->slug,
            'name' => $this->name,
            'description' => $this->description,
            'icon' => $this->icon,
            'color' => $this->color,
            'config_schema' => $this->config_schema,
            'input_schema' => $this->input_schema,
            'output_schema' => $this->output_schema,
            'credential_type' => $this->credential_type,
            'is_active' => $this->is_active,
            'is_custom' => true,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
