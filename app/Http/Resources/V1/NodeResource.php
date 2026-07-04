<?php

namespace App\Http\Resources\V1;

use App\Models\Node;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Node
 */
class NodeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'version' => $this->version,
            'name' => $this->name,
            'description' => $this->description,
            'icon' => $this->icon,
            'color' => $this->color,
            'node_kind' => $this->node_kind,
            'category' => new NodeCategoryResource($this->whenLoaded('category')),
            'config_schema' => $this->config_schema,
            'input_schema' => $this->input_schema,
            'output_schema' => $this->output_schema,
            'credential_type' => $this->credential_type,
            'cost_hint_usd' => $this->cost_hint_usd,
            'latency_hint_ms' => $this->latency_hint_ms,
            'is_premium' => $this->is_premium,
            'is_custom' => $this->is_custom,
            'docs_url' => $this->docs_url,
        ];
    }
}
