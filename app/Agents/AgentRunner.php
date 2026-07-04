<?php

namespace App\Agents;

use App\Agents\Internal\WorkflowAgent;
use App\Agents\Skills\SkillContextBuilder;
use App\Agents\Tools\SkillScriptTool;
use App\Agents\Tools\UpdateSkillTool;
use App\Agents\Tools\WorkflowNodeTool;
use App\Agents\Tools\WorkflowTool;
use App\Contracts\AgentRunnable;
use App\Models\Agent;
use App\Models\AgentSkill;
use App\Models\Node;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Enums\Lab;

class AgentRunner implements AgentRunnable
{
    public function __construct(
        private readonly SkillContextBuilder $skillContextBuilder,
    ) {}

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
        $agent->loadMissing(['toolConfigs', 'skills.references', 'skills.scripts']);

        $selectedSkills = $this->skillContextBuilder->select($agent->skills->all(), $message);

        return new WorkflowAgent(
            $this->buildSystemPrompt($agent, $selectedSkills),
            $this->buildTools($agent, $selectedSkills, $context),
        );
    }

    /**
     * @param  AgentSkill[]  $skills
     */
    private function buildSystemPrompt(Agent $agent, array $skills): string
    {
        $parts = [$agent->instructions];

        foreach ($skills as $skill) {
            $parts[] = "\n\n---\n## Skill: {$skill->name}";

            if ($skill->description) {
                $parts[] = $skill->description;
            }

            $parts[] = $skill->instructions;

            foreach ($skill->references as $reference) {
                $parts[] = "\n### {$reference->title}\n{$reference->content}";
            }
        }

        return implode("\n", $parts);
    }

    /**
     * @param  AgentSkill[]  $skills
     * @param  array<string, mixed>  $context
     * @return list<Tool>
     */
    private function buildTools(Agent $agent, array $skills, array $context): array
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

        foreach ($skills as $skill) {
            foreach ($skill->scripts->where('is_enabled', true) as $script) {
                $tools[] = new SkillScriptTool($script);
            }
        }

        if ($agent->skills->isNotEmpty()) {
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
