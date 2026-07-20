<?php

namespace App\Agents;

use App\Agents\Internal\WorkflowAgent;
use App\Agents\Tools\ListSkillsTool;
use App\Agents\Tools\LoadSkillTool;
use App\Agents\Tools\SkillScriptTool;
use App\Agents\Tools\UpdateSkillTool;
use App\Agents\Tools\WorkflowNodeTool;
use App\Agents\Tools\WorkflowTool;
use App\Contracts\AgentRunnable;
use App\Models\Agent;
use App\Models\Node;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Enums\Lab;

class AgentRunner implements AgentRunnable
{
    /**
     * Build the agent from a model record and run it with the given message.
     *
     * @param  array<string, mixed>  $context
     */
    public function run(string $message, array $context = []): string
    {
        $agent = $context['agent'] ?? null;

        if (! $agent instanceof Agent) {
            throw new \InvalidArgumentException('AgentRunner requires an Agent model in $context[\'agent\'].');
        }

        $workflowAgent = $this->build($agent, $message, $context);

        $response = $workflowAgent->prompt(
            $message,
            provider: $this->resolveProvider($agent->provider),
            model: $agent->model,
        );

        return (string) $response;
    }

    /**
     * Compile a runnable agent (system prompt + tools) from a model record.
     *
     * Exposed so interactive callers (conversations, streaming) can attach a
     * conversation participant before prompting.
     *
     * @param  array<string, mixed>  $context
     */
    public function build(Agent $agent, string $message, array $context = []): WorkflowAgent
    {
        $agent->loadMissing(['toolConfigs', 'skills.references', 'skills.scripts', 'knowledge', 'memories']);

        return new WorkflowAgent(
            $this->buildSystemPrompt($agent, $context),
            $this->buildTools($agent, $context),
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function buildSystemPrompt(Agent $agent, array $context = []): string
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

        if ($knowledge = $this->buildKnowledgeContext($agent)) {
            $parts[] = $knowledge;
        }

        if ($memory = $this->buildMemoryContext($agent, $context['user_id'] ?? null)) {
            $parts[] = $memory;
        }

        return implode("\n", $parts);
    }

    /**
     * Ground the agent with its active knowledge-base documents.
     */
    private function buildKnowledgeContext(Agent $agent): ?string
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
     */
    private function buildMemoryContext(Agent $agent, ?int $userId): ?string
    {
        $memories = $agent->memories
            ->filter(fn ($memory) => $memory->user_id === null || $memory->user_id === $userId);

        if ($memories->isEmpty()) {
            return null;
        }

        $parts = ["\n\n---\n## Remembered Context"];

        foreach ($memories as $memory) {
            $parts[] = "- {$memory->key}: {$memory->value}";
        }

        return implode("\n", $parts);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<Tool>
     */
    private function buildTools(Agent $agent, array $context): array
    {
        $tools = [];

        foreach ($agent->toolConfigs->where('is_enabled', true) as $config) {
            $nodeDefinition = Node::query()
                ->select(['id', 'name', 'description', 'input_schema', 'config_schema'])
                ->where('type', $config->node_type)
                ->first();

            $tools[] = new WorkflowNodeTool(
                nodeType: $config->node_type,
                toolName: $config->tool_name,
                toolDescription: $config->tool_description,
                inputSchema: $nodeDefinition?->input_schema ?? $nodeDefinition?->config_schema ?? [],
                credentials: $context['credentials'][$config->node_type] ?? [],
            );
        }

        foreach ($agent->skills as $skill) {
            foreach ($skill->scripts->where('is_enabled', true) as $script) {
                $tools[] = new SkillScriptTool($script);
            }
        }

        if ($agent->skills->isNotEmpty()) {
            $tools[] = new ListSkillsTool($agent->skills);
            $tools[] = new LoadSkillTool($agent->skills);
            $tools[] = new UpdateSkillTool($agent);
        }

        if ($agent->default_workflow_id) {
            $tools[] = new WorkflowTool($agent->default_workflow_id);
        }

        return $tools;
    }

    private function resolveProvider(?string $provider): ?Lab
    {
        if ($provider === null) {
            return null;
        }

        return Lab::tryFrom($provider);
    }
}
