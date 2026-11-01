<?php

namespace App\Http\Resources\Api\Internal\V1\Workflows;

use App\Models\User;
use App\Models\Workflows\WorkflowPublishRequest;
use App\Models\Workflows\WorkflowVersion;
use App\Services\Workflows\PublishRequests;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WorkflowPublishRequest
 */
class WorkflowPublishRequestResource extends JsonResource
{
    /**
     * The reviewed copy of the graph is left out when the query didn't select
     * it: a list only needs the history. Whether the draft or the live
     * version has moved on is only worked out alongside that copy, since it
     * reads the workflow's whole draft.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $hasGraph = array_key_exists('editor_graph', $this->resource->getAttributes());
        $user = $request->user();

        return [
            'id' => $this->id,
            'workflow_id' => $this->workflow_id,
            'status' => $this->status->value,
            'notes' => $this->notes,
            'editor_graph' => $this->when($hasGraph, fn () => $this->editor_graph),
            'workflow' => $this->when(! $hasGraph && $this->relationLoaded('workflow'), fn () => [
                'id' => $this->workflow->id,
                'name' => $this->workflow->name,
            ]),
            'requester' => $this->whenLoaded('requester', fn () => $this->person($this->requester)),
            'reviewer' => $this->whenLoaded('reviewer', fn () => $this->person($this->reviewer)),
            'reviewed_at' => $this->reviewed_at,
            'review_notes' => $this->review_notes,
            'base_version' => $this->whenLoaded('baseVersion', fn () => $this->version($this->baseVersion)),
            'published_version' => $this->whenLoaded('publishedVersion', fn () => $this->version($this->publishedVersion)),
            'draft_changed_since' => $this->when(
                $hasGraph && $this->isPending() && $this->relationLoaded('workflow'),
                fn () => $this->workflow->graphFingerprint() !== $this->graph_fingerprint,
            ),
            'live_version_changed_since' => $this->when(
                $hasGraph && $this->isPending() && $this->relationLoaded('workflow'),
                fn () => $this->workflow->current_version_id !== $this->base_version_id,
            ),
            'can_review' => $user instanceof User && $this->isPending() && app(PublishRequests::class)->canReview($this->resource, $user),
            'can_withdraw' => $user instanceof User && $this->isPending() && $this->requested_by === $user->id,
            'created_at' => $this->created_at,
        ];
    }

    /**
     * @return array{id: string, name: string}|null
     */
    private function person(?User $user): ?array
    {
        return $user === null ? null : ['id' => $user->id, 'name' => $user->name];
    }

    /**
     * @return array{id: string, version: int}|null
     */
    private function version(?WorkflowVersion $version): ?array
    {
        return $version === null ? null : ['id' => $version->id, 'version' => $version->version];
    }
}
