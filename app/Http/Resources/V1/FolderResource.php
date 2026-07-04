<?php

namespace App\Http\Resources\V1;

use App\Models\Folder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Folder
 */
class FolderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'color' => $this->color,
            'position' => $this->position,
            'parent_id' => $this->parent_id,
            'children' => FolderResource::collection($this->whenLoaded('children')),
            'workflow_count' => $this->whenCounted('workflows'),
            'created_at' => $this->created_at,
        ];
    }
}
