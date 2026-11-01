<?php

namespace App\Http\Resources\Api\Internal\V1\Agents;

use App\Models\Agents\WorkspaceAgentPolicy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WorkspaceAgentPolicy
 */
class WorkspaceAgentPolicyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'workspace_id' => $this->workspace_id,
            'max_autonomy_mode' => $this->max_autonomy_mode?->value,
            'allow_destructive_in_autopilot' => $this->allow_destructive_in_autopilot,
            'guardrails' => $this->guardrails ?? [],
            'approval_ttl_minutes' => $this->approval_ttl_minutes,
            'allow_chat_approvals' => $this->allow_chat_approvals,
            'updated_at' => $this->updated_at,
        ];
    }
}
