<?php

namespace App\Http\Resources\V1;

use App\Models\AgentSkill;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AgentSkill
 */
class AgentSkillResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'instructions' => $this->instructions,
            'is_shared' => $this->is_shared,
            'version' => $this->version,
            'sort_order' => $this->whenPivotLoaded('agent_agent_skill', fn () => $this->pivot->sort_order),
            'creator' => new UserResource($this->whenLoaded('creator')),
            'references' => AgentSkillReferenceResource::collection($this->whenLoaded('references')),
            'scripts' => AgentSkillScriptResource::collection($this->whenLoaded('scripts')),
            'references_count' => $this->whenCounted('references'),
            'scripts_count' => $this->whenCounted('scripts'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
