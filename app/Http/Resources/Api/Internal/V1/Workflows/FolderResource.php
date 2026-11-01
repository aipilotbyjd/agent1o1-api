<?php

namespace App\Http\Resources\Api\Internal\V1\Workflows;

use App\Models\Workflows\Folder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Folder
 */
class FolderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'type' => $this->type->value,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'color' => $this->color,
            'position' => $this->position,
            'children' => FolderResource::collection($this->whenLoaded('children')),
            'workflow_count' => $this->whenCounted('workflows'),
            'agent_count' => $this->whenCounted('agents'),
            'created_at' => $this->created_at,
        ];
    }
}
