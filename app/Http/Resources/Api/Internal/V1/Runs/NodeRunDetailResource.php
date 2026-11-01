<?php

namespace App\Http\Resources\Api\Internal\V1\Runs;

use App\Models\Runs\NodeRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The single-node view behind a run's canvas: everything `NodeRunResource`
 * carries plus what you only need when inspecting one step: the input it
 * received, its token/credit usage, engine state, retry budget, and the
 * child runs a `subflow`/`loop` node spawned.
 *
 * `callback_token` is deliberately absent: it is the bearer secret in a
 * `Wait` node's public callback URL, and every run-viewer in the workspace
 * can read this endpoint.
 *
 * @mixin NodeRun
 */
class NodeRunDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'run_id' => $this->run_id,
            'key' => $this->key,
            'type' => $this->type,
            'status' => $this->status->value,
            'input' => $this->input,
            'output' => $this->output,
            'usage' => $this->usage,
            'state' => $this->state,
            'error' => $this->error,
            'attempt' => $this->attempt,
            'max_attempts' => $this->max_attempts,
            'retry_delay_seconds' => $this->retry_delay_seconds,
            'awaiting_callback_until' => $this->callback_expires_at,
            'started_at' => $this->started_at,
            'finished_at' => $this->finished_at,
            'duration_ms' => $this->started_at !== null && $this->finished_at !== null
                ? $this->started_at->diffInMilliseconds($this->finished_at)
                : null,
            'credits_used' => $this->creditTransaction?->credits,
            'child_runs' => RunResource::collection($this->whenLoaded('childRuns')),
        ];
    }
}
