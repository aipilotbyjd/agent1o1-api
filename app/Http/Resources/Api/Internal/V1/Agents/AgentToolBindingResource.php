<?php

namespace App\Http\Resources\Api\Internal\V1\Agents;

use App\Models\Agents\AgentToolBinding;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AgentToolBinding
 */
class AgentToolBindingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'agent_id' => $this->agent_id,
            'node_type' => $this->node_type,
            'config' => $this->config,
            'exposed_fields' => $this->exposed_fields,
            'approval_policy' => $this->approval_policy,
            'created_at' => $this->created_at,
        ];
    }
}
