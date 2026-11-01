<?php

namespace App\Http\Resources\Api\Public\V1;

use App\Models\Agents\AgentMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AgentMessage
 */
class AgentMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'role' => $this->role->value,
            'content' => $this->content,
            // While true, the reply is on hold until its waiting actions are decided (`/actions`).
            'awaiting_approval' => $this->paused_state !== null,
            'created_at' => $this->created_at,
        ];
    }
}
