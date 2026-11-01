<?php

namespace App\Http\Resources\Api\Internal\V1\Agents;

use App\Models\Agents\AgentEvalCaseResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AgentEvalCaseResult
 */
class AgentEvalCaseResultResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'agent_eval_case_id' => $this->agent_eval_case_id,
            'case' => AgentEvalCaseResource::make($this->whenLoaded('evalCase')),
            'passed' => $this->passed,
            'output' => $this->output,
            'assertions' => $this->assertions,
            'usage' => $this->usage,
            'error' => $this->error,
        ];
    }
}
