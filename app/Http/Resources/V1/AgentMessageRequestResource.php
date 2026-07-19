<?php

namespace App\Http\Resources\V1;

use App\Models\AgentMessageRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AgentMessageRequest
 */
class AgentMessageRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'request_id' => $this->id,
            'status' => $this->status,
            'conversation_id' => $this->conversation_id,
            'agent_run_id' => $this->agent_run_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
