<?php

namespace App\Http\Resources\Api\Internal\V1\Nodes;

use App\Models\Nodes\NodeCategory;
use App\Services\Workflows\CatalogTaxonomy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin NodeCategory
 */
class NodeCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $taxonomy = app(CatalogTaxonomy::class);
        $slugs = $this->kind === 'app' ? ($this->functions ?: ['other']) : [];
        $functions = collect($slugs)
            ->map(fn (string $slug) => $taxonomy->functions()->get($slug))
            ->filter()
            ->values();
        $vendor = $this->vendor === null ? null : $taxonomy->vendors()->get($this->vendor);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'icon' => $this->icon,
            'color' => $this->color,
            'sort_order' => $this->sort_order,
            'kind' => $this->kind,
            'is_pinned' => (bool) ($this->resource->getAttributes()['is_pinned'] ?? false),
            'function' => $functions->first()?->slug,
            'functions' => $functions->map(fn ($function): array => [
                'slug' => $function->slug,
                'label' => $function->label,
                'order' => $function->sort_order,
            ])->all(),
            'vendor' => $vendor === null ? null : ['slug' => $vendor->slug, 'label' => $vendor->label],
            'status' => $this->status,
            'status_note' => $this->status_note,
        ];
    }
}
