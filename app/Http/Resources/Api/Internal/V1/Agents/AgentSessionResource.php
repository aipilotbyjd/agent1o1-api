<?php

namespace App\Http\Resources\Api\Internal\V1\Agents;

use App\Models\Agents\AgentSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AgentSession
 */
class AgentSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'agent_id' => $this->agent_id,
            'parent_session_id' => $this->parent_session_id,
            'user_id' => $this->user_id,
            'title' => $this->title,
            'status' => $this->status->value,
            // The conversation's own override; null means it runs under the agent's mode.
            'autonomy_mode' => $this->autonomy_mode?->value,
            'test_mode' => $this->test_mode,
            'pending_actions_count' => $this->whenCounted('pendingActions'),
            'last_activity_at' => $this->last_activity_at,
            'messages_count' => $this->whenCounted('messages'),
            'messages' => AgentMessageResource::collection($this->whenLoaded('messages')),
            'created_at' => $this->created_at,
        ];
    }
}
