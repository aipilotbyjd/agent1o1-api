<?php

namespace App\Http\Resources\V1\WorkflowBuilder;

use App\Models\WorkflowBuilderSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WorkflowBuilderSession
 */
class WorkflowBuilderSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'workflow_id' => $this->workflow_id,
            'nodes_draft' => $this->nodes_draft,
            'edges_draft' => $this->edges_draft,
            'draft_lock_version' => $this->draft_lock_version,
            'message_count' => $this->whenCounted('messages', fn () => $this->messages_count),
            'version_count' => $this->whenCounted('draftVersions', fn () => $this->draft_versions_count),
            'messages' => $this->whenLoaded('messages', fn () => WorkflowBuilderMessageResource::collection($this->messages)),
            'last_activity_at' => $this->last_activity_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
