<?php

namespace App\Http\Resources\Api\Internal\V1\Workflows;

use App\Models\Workflows\WorkflowVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WorkflowVersion
 */
class WorkflowVersionResource extends JsonResource
{
    /**
     * The graphs are left out when the query didn't select them: a version
     * list only needs the timeline, and every graph can be large.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $hasGraphs = array_key_exists('graph', $this->resource->getAttributes());

        return [
            'id' => $this->id,
            'workflow_id' => $this->workflow_id,
            'version' => $this->version,
            'graph' => $this->when($hasGraphs, fn () => $this->graph),
            'editor_graph' => $this->when($hasGraphs, fn () => $this->editorGraph()),
            'notes' => $this->notes,
            'published_by' => $this->published_by,
            'publisher' => $this->whenLoaded('publisher', fn () => $this->publisher === null ? null : [
                'id' => $this->publisher->id,
                'name' => $this->publisher->name,
            ]),
            'runs_count' => $this->whenCounted('runs'),
            'created_at' => $this->created_at,
        ];
    }
}
