<?php

namespace App\Http\Resources\V1;

use App\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Agent
 */
class AgentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'instructions' => $this->instructions,
            'model' => $this->model,
            'provider' => $this->provider,
            'max_steps' => $this->max_steps,
            'timeout_seconds' => $this->timeout_seconds,
            'is_active' => $this->is_active,
            'category' => $this->category,
            'metadata' => $this->metadata,
            'default_workflow_id' => $this->default_workflow_id,
            // Advanced capabilities (docs/AGENTS_ADVANCED_ROADMAP.md)
            'planning_enabled' => $this->planning_enabled,
            'reflection_enabled' => $this->reflection_enabled,
            'reflection_interval' => $this->reflection_interval,
            'child_agent_ids' => $this->child_agent_ids,
            'memory_auto_extract' => $this->memory_auto_extract,
            'memory_semantic_recall' => $this->memory_semantic_recall,
            'memory_recall_limit' => $this->memory_recall_limit,
            'code_execution_enabled' => $this->code_execution_enabled,
            'web_browsing_enabled' => $this->web_browsing_enabled,
            'tool_cache_enabled' => $this->tool_cache_enabled,
            'guardrails' => $this->guardrails,
            'max_tokens_per_run' => $this->max_tokens_per_run,
            'daily_token_budget' => $this->daily_token_budget,
            'daily_cost_budget' => $this->daily_cost_budget !== null ? (float) $this->daily_cost_budget : null,
            'is_paused' => $this->is_paused,
            'paused_reason' => $this->paused_reason,
            'creator' => new UserResource($this->whenLoaded('creator')),
            'tool_configs' => AgentToolConfigResource::collection($this->whenLoaded('toolConfigs')),
            'skills' => AgentSkillResource::collection($this->whenLoaded('skills')),
            'triggers' => TriggerResource::collection($this->whenLoaded('triggers')),
            'skills_count' => $this->whenCounted('skills'),
            'conversations_count' => $this->whenCounted('conversations'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
