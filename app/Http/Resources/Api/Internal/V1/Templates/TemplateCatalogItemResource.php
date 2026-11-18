<?php

namespace App\Http\Resources\Api\Internal\V1\Templates;

use App\Enums\Templates\TemplateType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One card in the combined template catalog: a plain row from
 * `TemplateCatalog`, not a model.
 *
 * @property object{id: string, type: string, workspace_id: ?string, name: string, description: ?string, category: ?string, icon: ?string, color: ?string, is_featured: int|bool, usage_count: int, items_count: int, created_at: ?string} $resource
 */
class TemplateCatalogItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $type = TemplateType::from($this->resource->type);

        return [
            'id' => $this->resource->id,
            'type' => $type->value,
            'type_label' => $type->label(),
            'name' => $this->resource->name,
            'description' => $this->resource->description,
            'category' => $this->resource->category,
            'icon' => $this->resource->icon,
            'color' => $this->resource->color,
            'is_featured' => (bool) $this->resource->is_featured,
            'is_own' => $this->resource->workspace_id !== null,
            'usage_count' => (int) $this->resource->usage_count,
            'items_count' => (int) $this->resource->items_count,
        ];
    }
}
