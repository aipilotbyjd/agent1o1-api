<?php

namespace App\Http\Resources\V1;

use App\Models\WorkflowTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WorkflowTemplate
 */
class WorkflowTemplateResource extends JsonResource
{
    public bool $detailed = false;

    public function detailed(bool $detailed = true): static
    {
        $this->detailed = $detailed;

        return $this;
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'category' => $this->category,
            'icon' => $this->icon,
            'color' => $this->color,
            'tags' => $this->tags,
            'is_featured' => $this->is_featured,
            'is_active' => $this->is_active,
            'usage_count' => $this->usage_count,
            'node_count' => is_array($this->nodes_data) ? count($this->nodes_data) : 0,
            $this->mergeWhen($this->detailed, fn () => [
                'nodes' => $this->nodes_data,
                'edges' => $this->edges_data,
            ]),
            'created_at' => $this->created_at,
        ];
    }
}
