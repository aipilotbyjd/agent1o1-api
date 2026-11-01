<?php

namespace App\Http\Resources\Api\Internal\V1\Ai;

use App\Models\Ai\ModelCatalog;
use App\Services\Ai\WorkspaceModelView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ModelCatalog
 *
 * `id` is included because `Agent.model_catalog_id` is what
 * `StoreAgentRequest`/`UpdateAgentRequest` actually validate against
 * (`exists:model_catalog,id`): a picker built on this response needs it to
 * submit a selection. Deliberately excludes `routes`, which real
 * backend(s), credentials, and priorities serve this entry is never exposed
 * to agent/workflow-facing API responses. See `Services\Ai\ModelCatalogResolver`.
 *
 * Availability, the reason a model can't run, whether it runs on the
 * workspace's own key, the admin's allow-list and default, and the cost
 * hint all come from the `WorkspaceModelView` it's given: the platform's
 * view when none is.
 */
class ModelCatalogResource extends JsonResource
{
    private ?WorkspaceModelView $view = null;

    public function withView(WorkspaceModelView $view): static
    {
        $this->view = $view;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'display_name' => $this->display_name,
            'brand' => $this->brand,
            'description' => $this->description,
            'strengths' => $this->strengths ?? [],
            'speed' => $this->speed?->value,
            'cost_tier' => $this->cost_tier,
            'capabilities' => $this->capabilities,
            'retires_at' => $this->retires_at,
            'replacement_slug' => $this->replacement_slug,
            ...$this->whenLoaded('routes', fn (): array => ($this->view ?? WorkspaceModelView::platform())->describe($this->resource), []),
        ];
    }
}
