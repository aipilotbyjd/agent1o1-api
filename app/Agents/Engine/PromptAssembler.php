<?php

namespace App\Agents\Engine;

use App\Models\Agent;
use App\Services\Agent\AgentMemoryService;
use App\Services\Agent\AgentReasoningService;

/**
 * Builds the full system prompt for a user agent: base instructions, attached
 * skills, active knowledge-base documents, recalled memories, and (when
 * planning is enabled) the plan drafted for this request.
 */
class PromptAssembler
{
    public function __construct(
        private readonly AgentMemoryService $memory,
        private readonly AgentReasoningService $reasoning,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>|null  $plan
     */
    public function assemble(Agent $agent, string $message, array $context, ?array $plan): string
    {
        $parts = [$agent->instructions];

        if ($agent->skills->isNotEmpty()) {
            $parts[] = "\n\n---\n## Skills available\nYou have skills attached. Call load_skill_tool "
                ."with a skill's slug when its description below is relevant to the current request, "
                ."before following it. Call list_skills_tool to see this list again.\n";

            foreach ($agent->skills as $skill) {
                $parts[] = "- {$skill->slug}: {$skill->description}";
            }
        }

        if ($knowledge = $this->knowledgeContext($agent)) {
            $parts[] = $knowledge;
        }

        if ($memory = $this->memoryContext($agent, $message, $context['user_id'] ?? null)) {
            $parts[] = $memory;
        }

        if ($plan) {
            $parts[] = $this->reasoning->renderPlan($plan);
        }

        return implode("\n", $parts);
    }

    /**
     * Ground the agent with its active knowledge-base documents.
     */
    private function knowledgeContext(Agent $agent): ?string
    {
        $items = $agent->knowledge->where('is_active', true);

        if ($items->isEmpty()) {
            return null;
        }

        $parts = ["\n\n---\n## Knowledge Base"];

        foreach ($items as $item) {
            $parts[] = "\n### {$item->title}\n{$item->content}";
        }

        return implode("\n", $parts);
    }

    /**
     * Recall persisted memories — agent-wide plus any scoped to the running user.
     * When semantic recall is enabled, only the top-K memories relevant to the
     * current message are pulled in instead of every row.
     */
    private function memoryContext(Agent $agent, string $message, ?int $userId): ?string
    {
        if ($agent->memory_semantic_recall) {
            $memories = $this->memory->recall($agent, $message, $userId, $agent->memory_recall_limit ?: 6);
        } else {
            $memories = $agent->memories
                ->filter(fn ($memory) => $memory->user_id === null || $memory->user_id === $userId);
        }

        if ($memories->isEmpty()) {
            return null;
        }

        $parts = ["\n\n---\n## Remembered Context"];

        foreach ($memories as $memory) {
            $parts[] = "- {$memory->key}: {$memory->value}";
        }

        return implode("\n", $parts);
    }
}
