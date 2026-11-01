<?php

namespace App\Http\Resources\Api\Internal\V1\Templates;

use App\Models\Templates\PageTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PageTemplate
 */
class PageTemplateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'source_page_id' => $this->source_page_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'category' => $this->category,
            'icon' => $this->icon,
            'color' => $this->color,
            'visibility' => $this->visibility,
            'usage_count' => $this->usage_count,
            'content' => $this->content,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
