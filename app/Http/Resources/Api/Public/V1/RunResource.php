<?php

namespace App\Http\Resources\Api\Public\V1;

use App\Models\Runs\Run;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Run
 */
class RunResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workflow_id' => $this->workflow_id,
            'status' => $this->status->value,
            'trigger_type' => $this->trigger_type,
            'input' => $this->input,
            'output' => $this->output,
            'error' => $this->error,
            'loop_index' => $this->loop_index,
            'started_at' => $this->started_at,
            'finished_at' => $this->finished_at,
            'duration_ms' => $this->durationMs(),
            'total_credits_used' => $this->totalCreditsUsed(),
            'created_at' => $this->created_at,
        ];
    }
}
