<?php

namespace App\Http\Resources\Api\Internal\V1\Agents;

use App\Models\Agents\AgentEvalSuite;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AgentEvalSuite
 */
class AgentEvalSuiteResource extends JsonResource
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
            'name' => $this->name,
            'description' => $this->description,
            'run_on_change' => $this->run_on_change,
            'case_count' => $this->whenCounted('cases'),
            'cases' => AgentEvalCaseResource::collection($this->whenLoaded('cases')),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
