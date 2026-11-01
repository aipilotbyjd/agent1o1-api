<?php

namespace App\Http\Resources\Api\Internal\V1\Agents;

use App\Http\Resources\Api\Internal\V1\Workflows\TagResource;
use App\Models\Agents\Agent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Agent
 */
class AgentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'folder_id' => $this->folder_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'icon' => $this->icon,
            'color' => $this->color,
            'instructions' => $this->instructions,
            'provider' => $this->provider,
            'model' => $this->model,
            'model_catalog_id' => $this->model_catalog_id,
            'model_catalog_slug' => $this->whenLoaded('modelCatalog', fn () => $this->modelCatalog?->slug),
            'temperature' => $this->temperature,
            'settings' => $this->settings,
            'allow_self_updates' => $this->allow_self_updates,
            'allow_skill_editing' => $this->allow_skill_editing,
            'allow_self_clone' => $this->allow_self_clone,
            'autonomy_mode' => $this->autonomy_mode?->value,
            'test_mode' => $this->test_mode,
            'allow_web_fetch' => $this->allow_web_fetch,
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'sessions_count' => $this->whenCounted('sessions'),
            'last_used_at' => $this->whenAggregated('sessions', 'last_activity_at', 'max'),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
