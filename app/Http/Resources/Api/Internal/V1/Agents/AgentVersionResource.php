<?php

namespace App\Http\Resources\Api\Internal\V1\Agents;

use App\Models\Agents\AgentVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AgentVersion
 */
class AgentVersionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'agent_id' => $this->agent_id,
            'version' => $this->version,
            'snapshot' => $this->snapshot,
            'changed_by' => $this->changed_by,
            'created_at' => $this->created_at,
        ];
    }
}
