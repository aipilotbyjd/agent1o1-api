<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Run
 */
class ExecutionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workflow_id' => $this->workflow_id,
            'status' => $this->status,
            'mode' => $this->mode,
            'started_at' => $this->started_at,
            'finished_at' => $this->finished_at,
            'duration_ms' => $this->duration_ms,
            'trigger_data' => $this->trigger_data,
            'result_data' => $this->result_data,
            'error' => $this->error,
            'attempt' => $this->attempt,
            'parent_execution_id' => $this->parent_execution_id,
            'credits_consumed' => $this->credits_consumed,
            'workflow' => new WorkflowResource($this->whenLoaded('workflow')),
            'nodes' => ExecutionNodeResource::collection($this->whenLoaded('nodes')),
            'triggered_by' => new UserResource($this->whenLoaded('triggeredBy')),
            'created_at' => $this->created_at,
        ];
    }
}
