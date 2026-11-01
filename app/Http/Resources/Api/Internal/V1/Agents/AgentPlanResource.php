<?php

namespace App\Http\Resources\Api\Internal\V1\Agents;

use App\Models\Agents\AgentPlan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AgentPlan
 */
class AgentPlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'agent_id' => $this->agent_id,
            'agent_session_id' => $this->agent_session_id,
            'run_id' => $this->run_id,
            'title' => $this->title,
            'summary' => $this->summary,
            'steps' => $this->steps,
            'status' => $this->status->value,
            'decided_by' => $this->decided_by,
            'decided_at' => $this->decided_at,
            'decision_note' => $this->decision_note,
            'created_at' => $this->created_at,
        ];
    }
}
