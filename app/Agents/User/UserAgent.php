<?php

namespace App\Agents\User;

use App\Agents\Contracts\AgentDefinition;
use App\Agents\Contracts\AgentType;
use App\Agents\Engine\PromptAssembler;
use App\Agents\Engine\ToolResolver;
use App\Models\Agent;

/**
 * Compiles a customer-created agent (an `agents` row) into the one shape the
 * engine executes: an AgentDefinition. This is the user-agent half of the
 * two-type design; internal agents produce definitions from code instead.
 */
class UserAgent
{
    public function __construct(
        private readonly PromptAssembler $prompts,
        private readonly ToolResolver $tools,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>|null  $plan
     */
    public function compile(Agent $agent, string $message, array $context = [], ?array $plan = null): AgentDefinition
    {
        $agent->loadMissing(['workspace', 'toolConfigs', 'skills.references', 'skills.scripts', 'knowledge', 'memories']);

        $tools = $this->tools->resolve($agent, $context);

        return new AgentDefinition(
            name: $agent->slug ?? $agent->name,
            type: AgentType::User,
            systemPrompt: $this->prompts->assemble($agent, $message, $context, $plan),
            provider: $agent->provider,
            model: $agent->model,
            tools: $tools,
            maxSteps: $agent->max_steps ?? 15,
            timeoutSeconds: $agent->timeout_seconds ?? 180,
        );
    }
}
