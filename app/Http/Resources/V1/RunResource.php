<?php

namespace App\Http\Resources\V1;

use App\Models\Run;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Cross-type view of a run (workflow execution or agent run). Common lifecycle
 * fields are always present; type-specific fields are included only for the
 * relevant runnable so a single client can render both.
 *
 * @mixin Run
 */
class RunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'runnable_type' => $this->runnable_type,
            'runnable_id' => $this->runnable_id,
            'workspace_id' => $this->workspace_id,
            'status' => $this->status,
            'started_at' => $this->started_at,
            'finished_at' => $this->finished_at,
            'duration_ms' => $this->duration_ms,
            'error' => $this->error,
            'created_at' => $this->created_at,

            // Workflow-execution fields.
            $this->mergeWhen($this->runnable_type === 'workflow', fn () => [
                'workflow_id' => $this->workflow_id,
                'mode' => $this->mode,
                'trigger_data' => $this->trigger_data,
                'result_data' => $this->result_data,
                'attempt' => $this->attempt,
                'credits_consumed' => $this->credits_consumed,
            ]),

            // Agent-run fields.
            $this->mergeWhen($this->runnable_type === 'agent', fn () => [
                'agent_id' => $this->agent_id,
                'source' => $this->source,
                'input' => $this->input,
                'output' => $this->output,
                'provider' => $this->provider,
                'model' => $this->model,
                'total_tokens' => $this->total_tokens,
                'estimated_cost' => $this->estimated_cost,
            ]),
        ];
    }
}
