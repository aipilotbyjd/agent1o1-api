<?php

namespace App\Http\Resources\V1;

use App\Models\Run;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Cross-type view of a run (workflow execution or agent run). Common lifecycle
 * fields are always present; type-specific fields are included only for the
 * relevant runnable so a single client can render both. Relations (workflow,
 * nodes, steps, internal runs) are surfaced only when eager-loaded.
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
                'workflow_id' => $this->runnable_id,
                'mode' => $this->mode,
                'trigger_data' => $this->trigger_data,
                'result_data' => $this->result_data,
                'attempt' => $this->attempt,
                'parent_execution_id' => $this->parent_execution_id,
                'credits_consumed' => $this->credits_consumed,
                'workflow' => new WorkflowResource($this->whenLoaded('workflow')),
                'nodes' => ExecutionNodeResource::collection($this->whenLoaded('nodes')),
                'triggered_by' => new UserResource($this->whenLoaded('triggeredBy')),
            ]),

            // Agent-run fields.
            $this->mergeWhen($this->runnable_type === 'agent', fn () => [
                'agent_id' => $this->runnable_id,
                'conversation_id' => $this->conversation_id,
                'trigger_id' => $this->trigger_id,
                'source' => $this->source,
                'input' => $this->input,
                'output' => $this->output,
                'provider' => $this->provider,
                'model' => $this->model,
                'prompt_tokens' => $this->prompt_tokens,
                'completion_tokens' => $this->completion_tokens,
                'total_tokens' => $this->total_tokens,
                'estimated_cost' => $this->estimated_cost,
                'metadata' => $this->metadata,
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
            ]),
        ];
    }
}
