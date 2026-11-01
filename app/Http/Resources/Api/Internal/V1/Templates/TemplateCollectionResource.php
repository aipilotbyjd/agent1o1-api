<?php

namespace App\Http\Resources\Api\Internal\V1\Templates;

use App\Models\Templates\TemplateCollection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TemplateCollection
 */
class TemplateCollectionResource extends JsonResource
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
            'visibility' => $this->visibility,
            'items' => TemplateCollectionItemResource::collection($this->whenLoaded('items')),
            'item_count' => $this->whenCounted('items'),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
