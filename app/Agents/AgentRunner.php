<?php

namespace App\Agents;

use App\Agents\Internal\WorkflowAgent;
use App\Agents\Tools\AgentTool;
use App\Agents\Tools\CachedTool;
use App\Agents\Tools\CodeExecutionTool;
use App\Agents\Tools\ExportArtifactTool;
use App\Agents\Tools\ListSkillsTool;
use App\Agents\Tools\LoadSkillTool;
use App\Agents\Tools\SkillScriptTool;
use App\Agents\Tools\UpdateSkillTool;
use App\Agents\Tools\WebBrowseTool;
use App\Agents\Tools\WorkflowNodeTool;
use App\Agents\Tools\WorkflowTool;
use App\Contracts\AgentRunnable;
use App\Models\Agent;
use App\Models\Node;
use App\Services\Agent\AgentMemoryService;
use App\Services\Agent\AgentReasoningService;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Tools\ToolNameResolver;

class AgentRunner implements AgentRunnable
{
    public function __construct(
        private readonly ?AgentMemoryService $memory = null,
        private readonly ?AgentReasoningService $reasoning = null,
    ) {}

    private function memory(): AgentMemoryService
    {
        return $this->memory ?? app(AgentMemoryService::class);
    }

    private function reasoning(): AgentReasoningService
    {
        return $this->reasoning ?? app(AgentReasoningService::class);
    }

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
        $agent->loadMissing(['workspace', 'toolConfigs', 'skills.references', 'skills.scripts', 'knowledge', 'memories']);

        $tools = $this->buildTools($agent, $context);

        // Planner/executor split (roadmap item 1): draft a plan first and fold it
        // into the system prompt so the executor loop works against it. The plan
        // is surfaced back to callers via $context so they can persist it.
        $plan = $context['plan'] ?? $this->reasoning()->plan($agent, $message, $this->toolNames($tools));

        return new WorkflowAgent(
            $this->buildSystemPrompt($agent, $message, $context, $plan),
            $tools,
        );
    }

    /**
     * Draft a plan for the given message without building the whole agent —
     * used by streaming callers that want to persist the plan on the run.
     *
     * @param  array<string, mixed>  $context
     * @return array{goal: string, needs_tools: bool, steps: list<array{title: string, detail?: string}>}|null
     */
    public function plan(Agent $agent, string $message, array $context = []): ?array
    {
        $agent->loadMissing(['toolConfigs', 'skills.scripts']);

        return $this->reasoning()->plan($agent, $message, $this->toolNames($this->buildTools($agent, $context)));
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>|null  $plan
     */
    private function buildSystemPrompt(Agent $agent, string $message, array $context, ?array $plan): string
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

        if ($memory = $this->buildMemoryContext($agent, $message, $context['user_id'] ?? null)) {
            $parts[] = $memory;
        }

        if ($plan) {
            $parts[] = $this->reasoning()->renderPlan($plan);
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
     * When semantic recall is enabled, only the top-K memories relevant to the
     * current message are pulled in instead of every row (roadmap item 4).
     */
    private function buildMemoryContext(Agent $agent, string $message, ?int $userId): ?string
    {
        if ($agent->memory_semantic_recall) {
            $memories = $this->memory()->recall($agent, $message, $userId, $agent->memory_recall_limit ?: 6);
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

        // Multi-agent delegation (roadmap item 3): expose each child agent as a
        // callable tool so this agent can hand off sub-tasks.
        if (($context['allow_sub_agents'] ?? true) && ! empty($agent->child_agent_ids)) {
            foreach ($agent->childAgents() as $child) {
                $tools[] = new AgentTool($child, $context['credentials'] ?? []);
            }
        }

        // Code execution sandbox (item 5) and browsing (item 6).
        if ($agent->code_execution_enabled) {
            $tools[] = new CodeExecutionTool;
        }

        if ($agent->web_browsing_enabled) {
            $tools[] = new WebBrowseTool;
        }

        $tools[] = new ExportArtifactTool(
            $agent,
            $agent->workspace,
            $context['conversation_id'] ?? null,
            $context['agent_run_id'] ?? null,
            $context['user_id'] ?? null,
        );

        // Tool result caching / dedup (item 8): wrap read-only tools so repeated
        // identical calls within this run are served from memory.
        if ($agent->tool_cache_enabled) {
            $tools = array_map(
                fn (Tool $tool) => CachedTool::shouldCache($tool) ? new CachedTool($tool) : $tool,
                $tools,
            );
        }

        return $tools;
    }

    /**
     * @param  list<Tool>  $tools
     * @return list<string>
     */
    private function toolNames(array $tools): array
    {
        return array_values(array_map(
            fn (Tool $tool) => ToolNameResolver::resolve($tool),
            $tools,
        ));
    }

    private function resolveProvider(?string $provider): ?Lab
    {
        if ($provider === null) {
            return null;
        }

        return Lab::tryFrom($provider);
    }
}
