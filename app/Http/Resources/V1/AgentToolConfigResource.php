<?php

namespace App\Http\Resources\V1;

use App\Models\AgentToolConfig;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AgentToolConfig
 */
class AgentToolConfigResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'node_type' => $this->node_type,
            'tool_name' => $this->tool_name,
            'tool_description' => $this->tool_description,
            'is_enabled' => $this->is_enabled,
            'sort_order' => $this->sort_order,
        ];
    }
}
