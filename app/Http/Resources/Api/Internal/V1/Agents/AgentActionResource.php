<?php

namespace App\Http\Resources\Api\Internal\V1\Agents;

use App\Models\Agents\AgentAction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * An agent action as an approval card or an action-log row: what the agent
 * wanted to do, why it was (or wasn't) asked, and how it ended.
 *
 * @mixin AgentAction
 */
class AgentActionResource extends JsonResource
{
    /** How much of a result the card shows; a fetched page can run to megabytes. */
    public const RESULT_LIMIT = 4000;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'agent_id' => $this->agent_id,
            'agent_session_id' => $this->agent_session_id,
            'run_id' => $this->run_id,
            'node_run_id' => $this->node_run_id,
            'agent_message_id' => $this->agent_message_id,
            'plan_id' => $this->plan_id,
            'tool_call_id' => $this->tool_call_id,
            'tool_name' => $this->tool_name,
            'tool_kind' => $this->tool_kind->value,
            'effect' => $this->effect->value,
            'arguments' => $this->arguments,
            'edited_arguments' => $this->edited_arguments,
            'outcome' => $this->outcome->value,
            'status' => $this->status->value,
            'reason' => $this->reason,
            'risk' => $this->risk?->value,
            'review_reason' => $this->review['reason'] ?? null,
            'result' => $this->result === null ? null : Str::limit($this->result, self::RESULT_LIMIT),
            'approvers' => $this->approvers,
            'requested_at' => $this->requested_at,
            'expires_at' => $this->expires_at,
            'decided_by' => $this->decided_by,
            'decided_at' => $this->decided_at,
            'decision_note' => $this->decision_note,
            'decision_channel' => $this->decision_channel,
            'stops_turn' => $this->stops_turn,
            'executed_at' => $this->executed_at,
            'agent' => $this->whenLoaded('agent', fn (): array => [
                'id' => $this->agent->id,
                'name' => $this->agent->name,
                'icon' => $this->agent->icon,
                'color' => $this->agent->color,
            ]),
            'session' => $this->whenLoaded('session', fn (): ?array => $this->session === null ? null : [
                'id' => $this->session->id,
                'title' => $this->session->title,
                'user_id' => $this->session->user_id,
                'parent_session_id' => $this->session->parent_session_id,
            ]),
            'created_at' => $this->created_at,
        ];
    }
}
