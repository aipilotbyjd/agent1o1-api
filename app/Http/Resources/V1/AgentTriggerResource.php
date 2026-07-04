<?php

namespace App\Http\Resources\V1;

use App\Models\AgentTrigger;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AgentTrigger
 */
class AgentTriggerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'agent_id' => $this->agent_id,
            'type' => $this->type,
            'config' => $this->config,
            'initial_message' => $this->initial_message,
            'is_active' => $this->is_active,
            'webhook_url' => $this->when(
                $this->type === 'webhook',
                fn () => url("/api/v1/agent-webhooks/{$this->id}"),
            ),
            'last_fired_at' => $this->last_fired_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
