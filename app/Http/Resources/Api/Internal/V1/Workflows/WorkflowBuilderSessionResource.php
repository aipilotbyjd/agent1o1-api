<?php

namespace App\Http\Resources\Api\Internal\V1\Workflows;

use App\Models\Workflows\Builder\WorkflowBuilderSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WorkflowBuilderSession
 */
class WorkflowBuilderSessionResource extends JsonResource
{
    /**
     * What a session list selects: everything but `draft_graph`, which is
     * then left out of each item (`whenHas`): a list shows titles and
     * status, and a full graph per session would dwarf them.
     */
    public const array LIST_COLUMNS = [
        'id', 'workspace_id', 'user_id', 'workflow_id', 'workflow_graph_hash', 'title',
        'draft_lock_version', 'status', 'last_activity_at', 'created_at', 'updated_at',
    ];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'user_id' => $this->user_id,
            'workflow_id' => $this->workflow_id,
            'title' => $this->title,
            'draft_graph' => $this->whenHas('draft_graph'),
            'draft_lock_version' => $this->draft_lock_version,
            'status' => $this->status,
            'last_activity_at' => $this->last_activity_at,
            'messages' => WorkflowBuilderMessageResource::collection($this->whenLoaded('messages')),
            'messages_count' => $this->whenCounted('messages'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
