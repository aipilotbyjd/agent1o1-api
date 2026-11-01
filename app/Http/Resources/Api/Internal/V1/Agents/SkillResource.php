<?php

namespace App\Http\Resources\Api\Internal\V1\Agents;

use App\Models\Agents\Skill;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Skill
 */
class SkillResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'category' => $this->category,
            'icon' => $this->icon,
            'color' => $this->color,
            'tags' => $this->tags,
            'instructions' => $this->instructions,
            'is_shared' => $this->is_shared,
            'version' => $this->version,
            'skill_source_id' => $this->skill_source_id,
            'source_two_way' => $this->isSynced() && (bool) $this->source?->two_way,
            'source_path' => $this->source_path,
            'source_url' => $this->isSynced() ? $this->source?->url($this->source_path) : null,
            'origin_url' => $this->origin_url,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
