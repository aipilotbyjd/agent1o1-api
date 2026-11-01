<?php

namespace App\Http\Resources\Api\Internal\V1\Agents;

use App\Ai\Tools\InvokeAgentTool;
use App\Http\Resources\Api\Internal\V1\Artifacts\ArtifactResource;
use App\Models\Agents\AgentMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * @mixin AgentMessage
 */
class AgentMessageResource extends JsonResource
{
    /** How much of a tool's output the chat timeline shows; a web page or file can run to megabytes. */
    public const TOOL_OUTPUT_LIMIT = 4000;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'agent_session_id' => $this->agent_session_id,
            'role' => $this->role->value,
            'content' => $this->content,
            'skill' => $this->whenLoaded('skill', fn () => $this->skill === null ? null : [
                'id' => $this->skill->id,
                'name' => $this->skill->name,
                'slug' => $this->skill->slug,
                'icon' => $this->skill->icon,
                'color' => $this->skill->color,
                'category' => $this->skill->category,
            ]),
            'attachments' => ArtifactResource::collection($this->whenLoaded('attachments')),
            'tool_calls' => $this->tool_calls,
            'tool_results' => collect($this->tool_results ?? [])
                ->map(fn (array $result): array => [
                    'id' => $result['id'],
                    'name' => $result['name'],
                    'output' => Str::limit((string) $result['result'], self::TOOL_OUTPUT_LIMIT),
                ])
                ->values(),
            // Tool call id => the subagent task it started, for the chat's status cards.
            'subagent_task_ids' => (object) collect($this->tool_results ?? [])
                ->filter(fn (array $result): bool => ($result['name'] ?? null) === InvokeAgentTool::NAME)
                ->mapWithKeys(fn (array $result): array => [$result['id'] => json_decode((string) $result['result'], true)['task_id'] ?? null])
                ->filter()
                ->all(),
            // Set while the turn waits on approvals: which calls are waiting.
            'awaiting_approval' => $this->paused_state !== null,
            'pending_tool_call_ids' => $this->paused_state['pending_tool_call_ids'] ?? [],
            'actions' => AgentActionResource::collection($this->whenLoaded('actions')),
            'usage' => $this->usage,
            'created_at' => $this->created_at,
        ];
    }
}
