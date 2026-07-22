<?php

namespace App\Agents\Engine;

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
use App\Models\Agent;
use App\Models\Node;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\ToolNameResolver;

/**
 * Turns a user agent's configuration (tool configs, skills, feature toggles)
 * into the concrete list of tools its run may call.
 */
class ToolResolver
{
    /**
     * @param  array<string, mixed>  $context
     * @return list<Tool>
     */
    public function resolve(Agent $agent, array $context): array
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

        // Multi-agent delegation: expose each child agent as a callable tool so
        // this agent can hand off sub-tasks.
        if (($context['allow_sub_agents'] ?? true) && ! empty($agent->child_agent_ids)) {
            foreach ($agent->childAgents() as $child) {
                $tools[] = new AgentTool($child, $context['credentials'] ?? []);
            }
        }

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

        // Tool result caching / dedup: wrap read-only tools so repeated identical
        // calls within this run are served from memory.
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
    public function names(array $tools): array
    {
        return array_values(array_map(
            fn (Tool $tool) => ToolNameResolver::resolve($tool),
            $tools,
        ));
    }
}
