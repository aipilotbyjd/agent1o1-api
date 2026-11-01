<?php

namespace App\Http\Resources\Api\Internal\V1\Templates;

use App\Models\Templates\AgentTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AgentTemplate
 */
class AgentTemplateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'source_agent_id' => $this->source_agent_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'category' => $this->category,
            'icon' => $this->icon,
            'color' => $this->color,
            'visibility' => $this->visibility,
            'usage_count' => $this->usage_count,
            'config' => $this->config,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
