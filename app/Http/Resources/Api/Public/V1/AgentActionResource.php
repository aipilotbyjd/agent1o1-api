<?php

namespace App\Http\Resources\Api\Public\V1;

use App\Models\Agents\AgentAction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An agent action as an API caller sees it: enough to show or build their
 * own approval step, without internal fields like who reviewed it.
 *
 * @mixin AgentAction
 */
class AgentActionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'agent_session_id' => $this->agent_session_id,
            'tool_name' => $this->tool_name,
            'effect' => $this->effect->value,
            'arguments' => $this->effectiveArguments(),
            'status' => $this->status->value,
            'reason' => $this->reason['detail'] ?? null,
            'risk' => $this->risk?->value,
            'requested_at' => $this->requested_at,
            'expires_at' => $this->expires_at,
            'decided_at' => $this->decided_at,
            'created_at' => $this->created_at,
        ];
    }
}
