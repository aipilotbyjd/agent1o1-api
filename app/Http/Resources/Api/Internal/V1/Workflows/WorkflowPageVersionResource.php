<?php

namespace App\Http\Resources\Api\Internal\V1\Workflows;

use App\Models\Workflows\WorkflowPageVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A page version for the history list; the content only when one version is
 * opened.
 *
 * @mixin WorkflowPageVersion
 */
class WorkflowPageVersionResource extends JsonResource
{
    protected bool $withContent = false;

    public function withContent(): static
    {
        $this->withContent = true;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'kind' => $this->kind,
            'label' => $this->label,
            'has_variant' => $this->variant_content !== null,
            'content' => $this->when($this->withContent, fn () => $this->content),
            'variant_content' => $this->when($this->withContent, fn () => $this->variant_content),
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator === null ? null : ['id' => $this->creator->id, 'name' => $this->creator->name]),
            'created_at' => $this->created_at,
        ];
    }
}
