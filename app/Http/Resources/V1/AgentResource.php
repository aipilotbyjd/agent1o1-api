<?php

namespace App\Http\Resources\V1;

use App\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Agent
 */
class AgentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'instructions' => $this->instructions,
            'model' => $this->model,
            'provider' => $this->provider,
            'max_steps' => $this->max_steps,
            'timeout_seconds' => $this->timeout_seconds,
            'is_active' => $this->is_active,
            'category' => $this->category,
            'metadata' => $this->metadata,
            'default_workflow_id' => $this->default_workflow_id,
            'creator' => new UserResource($this->whenLoaded('creator')),
            'tool_configs' => AgentToolConfigResource::collection($this->whenLoaded('toolConfigs')),
            'skills' => AgentSkillResource::collection($this->whenLoaded('skills')),
            'triggers' => AgentTriggerResource::collection($this->whenLoaded('triggers')),
            'skills_count' => $this->whenCounted('skills'),
            'conversations_count' => $this->whenCounted('conversations'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
