<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Run
 */
class AgentRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'agent_id' => $this->agent_id,
            'conversation_id' => $this->conversation_id,
            'trigger_id' => $this->trigger_id,
            'source' => $this->source,
            'status' => $this->status,
            'input' => $this->input,
            'output' => $this->output,
            'error' => $this->error,
            'provider' => $this->provider,
            'model' => $this->model,
            'prompt_tokens' => $this->prompt_tokens,
            'completion_tokens' => $this->completion_tokens,
            'total_tokens' => $this->total_tokens,
            'duration_ms' => $this->duration_ms,
            'metadata' => $this->metadata,
            'started_at' => $this->started_at,
            'finished_at' => $this->finished_at,
            'steps_count' => $this->whenCounted('steps'),
            'steps' => AiAgentStepResource::collection($this->whenLoaded('steps')),
            // System-overhead breakdown: internal agent calls (planner,
            // reflection, moderation, ...) attributed to this run.
            'internal_runs' => InternalAgentRunResource::collection($this->whenLoaded('internalRuns')),
            'internal_cost' => $this->whenLoaded(
                'internalRuns',
                fn () => (float) $this->internalRuns->sum('estimated_cost'),
            ),
            'internal_tokens' => $this->whenLoaded(
                'internalRuns',
                fn () => (int) $this->internalRuns->sum('total_tokens'),
            ),
            'created_at' => $this->created_at,
        ];
    }
}
