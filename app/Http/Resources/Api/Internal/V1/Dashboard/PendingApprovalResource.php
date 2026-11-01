<?php

namespace App\Http\Resources\Api\Internal\V1\Dashboard;

use App\Models\Workflows\WorkflowApproval;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A `HumanApproval` node waiting on a decision, carrying enough context to be
 * actionable straight from a dashboard card, which node of which workflow,
 * and how long it has been sitting there. `WorkflowApprovalResource` stays
 * the shape returned when deciding one; this is the shape for a queue of
 * them, where a bare `run_id` tells the reader nothing.
 *
 * The decision fields are omitted rather than serialised as nulls: every row
 * in this collection is undecided by definition.
 *
 * @mixin WorkflowApproval
 */
class PendingApprovalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'run_id' => $this->run_id,
            'node_run_id' => $this->node_run_id,
            'requested_at' => $this->requested_at,
            'waiting_seconds' => $this->requested_at === null ? null : (int) $this->requested_at->diffInSeconds(now()),
            'node' => $this->whenLoaded('nodeRun', fn (): array => [
                'key' => $this->nodeRun->key,
                'type' => $this->nodeRun->type,
            ]),
            // What the person deciding is asked, so a card can show the form.
            'form' => $this->whenLoaded('nodeRun', fn (): ?array => $this->nodeRun->output['approval_form'] ?? null),
            'run' => $this->whenLoaded('run', fn (): array => [
                'id' => $this->run->id,
                'status' => $this->run->status->value,
                'trigger_type' => $this->run->trigger_type,
                'started_at' => $this->run->started_at,
                'workflow' => $this->run->relationLoaded('workflow') && $this->run->workflow !== null ? [
                    'id' => $this->run->workflow->id,
                    'name' => $this->run->workflow->name,
                ] : null,
            ]),
        ];
    }
}
