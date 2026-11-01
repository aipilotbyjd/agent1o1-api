<?php

namespace App\Http\Resources\Api\Internal\V1\Runs;

use App\Http\Resources\Api\Internal\V1\Agents\AgentMessageResource;
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
            'workspace_id' => $this->workspace_id,
            'runnable_type' => $this->runnable_type,
            'runnable_id' => $this->runnable_id,
            'workflow_id' => $this->workflow_id,
            'workflow' => $this->whenLoaded('workflow', fn () => $this->workflow ? ['id' => $this->workflow->id, 'name' => $this->workflow->name] : null),
            'workflow_version_id' => $this->workflow_version_id,
            'retried_from_run_id' => $this->retried_from_run_id,
            'status' => $this->status->value,
            'trigger_type' => $this->trigger_type,
            'input' => $this->input,
            'output' => $this->output,
            'error' => $this->error,
            'node_runs' => NodeRunResource::collection($this->whenLoaded('nodeRuns')),
            'agent' => $this->whenLoaded('runnable', fn () => $this->agentSummary()),
            'agent_reply' => AgentMessageResource::make($this->whenLoaded('agentReply')),
            // Only on the single-run view, which loads the reply: a system
            // prompt per row would bloat every run list.
            'agent_context' => $this->when($this->relationLoaded('agentReply'), fn () => $this->agent_context),
            'triggered_by' => $this->triggered_by,
            'loop_index' => $this->loop_index,
            'started_at' => $this->started_at,
            'finished_at' => $this->finished_at,
            'duration_ms' => $this->durationMs(),
            'total_credits_used' => $this->totalCreditsUsed(),
            'created_at' => $this->created_at,
        ];
    }

    /**
     * @return array{id: string, name: string, icon: ?string, color: ?string}|null
     */
    private function agentSummary(): ?array
    {
        $agent = $this->owningAgent();

        return $agent ? [
            'id' => $agent->id,
            'name' => $agent->name,
            'icon' => $agent->icon,
            'color' => $agent->color,
        ] : null;
    }
}
