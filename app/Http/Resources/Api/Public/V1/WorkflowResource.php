<?php

namespace App\Http\Resources\Api\Public\V1;

use App\Models\Workflows\Workflow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Deliberately narrower than `Api\Internal\V1\Workflows\WorkflowResource`:
 * run/list/inspect only, no builder-editing internals. See
 * docs/STRUCTURE.md's "Public vs. Internal API" section.
 *
 * @mixin Workflow
 */
class WorkflowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'status' => $this->status,
            'is_published' => $this->isPublished(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
