<?php

namespace App\Http\Resources\V1;

use App\Models\ExecutionNode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ExecutionNode
 */
class ExecutionNodeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'node_id' => $this->node_id,
            'node_run_key' => $this->node_run_key,
            'node_type' => $this->node_type,
            'node_name' => $this->node_name,
            'status' => $this->status,
            'started_at' => $this->started_at,
            'finished_at' => $this->finished_at,
            'duration_ms' => $this->duration_ms,
            'attempt' => $this->attempt,
            'input_data' => $this->input_data,
            'output_data' => $this->output_data,
            'error' => $this->error,
            'sequence' => $this->sequence,
            'loop_index' => $this->loop_index,
        ];
    }
}
