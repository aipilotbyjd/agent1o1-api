<?php

namespace App\Http\Resources\V1;

use App\Models\AgentEvalSuite;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AgentEvalSuite
 */
class AgentEvalSuiteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'agent_id' => $this->agent_id,
            'name' => $this->name,
            'description' => $this->description,
            'cases' => AgentEvalCaseResource::collection($this->whenLoaded('cases')),
            'cases_count' => $this->whenCounted('cases'),
            'latest_run' => new AgentEvalRunResource($this->whenLoaded('runs', fn () => $this->runs->first())),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
