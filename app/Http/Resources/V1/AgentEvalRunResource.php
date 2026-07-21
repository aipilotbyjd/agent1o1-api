<?php

namespace App\Http\Resources\V1;

use App\Models\AgentEvalRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AgentEvalRun
 */
class AgentEvalRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'suite_id' => $this->suite_id,
            'agent_id' => $this->agent_id,
            'status' => $this->status,
            'total' => $this->total,
            'passed' => $this->passed,
            'failed' => $this->failed,
            'pass_rate' => $this->total > 0 ? round($this->passed / $this->total, 4) : null,
            'results' => $this->results,
            'error' => $this->error,
            'finished_at' => $this->finished_at,
            'created_at' => $this->created_at,
        ];
    }
}
