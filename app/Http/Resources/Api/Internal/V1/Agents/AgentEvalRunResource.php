<?php

namespace App\Http\Resources\Api\Internal\V1\Agents;

use App\Models\Agents\AgentEvalRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AgentEvalRun
 */
class AgentEvalRunResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'agent_eval_suite_id' => $this->agent_eval_suite_id,
            // Which behavior was graded: an eval result read without this is
            // not interpretable. See the migration's docblock.
            'agent_version_id' => $this->agent_version_id,
            'trigger' => $this->trigger->value,
            'status' => $this->status->value,
            'passed' => $this->passed,
            'failed' => $this->failed,
            'regressed' => $this->regressed,
            'error' => $this->error,
            'results' => AgentEvalCaseResultResource::collection($this->whenLoaded('results')),
            'triggered_by' => $this->triggered_by,
            'started_at' => $this->started_at,
            'finished_at' => $this->finished_at,
            'created_at' => $this->created_at,
        ];
    }
}
