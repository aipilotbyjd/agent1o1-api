<?php

namespace App\Http\Resources\Api\Internal\V1\Agents;

use App\Models\Agents\AgentSessionEvaluation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AgentSessionEvaluation
 */
class AgentSessionEvaluationResource extends JsonResource
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
            'status' => $this->status->value,
            'grade' => $this->grade?->value,
            'call_successful' => $this->call_successful,
            'sentiment' => $this->sentiment,
            'summary' => $this->summary,
            'criteria_results' => $this->criteria_results ?? [],
            'data_results' => $this->data_results ?? [],
            'applied_tags' => $this->applied_tags ?? [],
            'error' => $this->error,
            'evaluated_at' => $this->evaluated_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
