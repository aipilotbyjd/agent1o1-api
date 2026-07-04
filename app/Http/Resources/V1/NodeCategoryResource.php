<?php

namespace App\Http\Resources\V1;

use App\Models\NodeCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin NodeCategory
 */
class NodeCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'icon' => $this->icon,
            'color' => $this->color,
            'kind' => $this->kind,
            'sort_order' => $this->sort_order,
            'nodes_count' => $this->whenCounted('nodes'),
            'nodes' => NodeResource::collection($this->whenLoaded('nodes')),
        ];
    }
}
