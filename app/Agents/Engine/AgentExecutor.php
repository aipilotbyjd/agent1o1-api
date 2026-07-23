<?php

namespace App\Agents\Engine;

use App\Agents\Contracts\AgentDefinition;
use App\Agents\User\ConversationAgent;
use App\Agents\User\UserAgent;
use App\Models\Agent;
use App\Services\Agent\AgentReasoningService;
use Laravel\Ai\Enums\Lab;

/**
 * The one execution pipeline for user agents. Compiles the agent to an
 * AgentDefinition (prompt + tools), runs the optional planning pass, and
 * executes the LLM loop. Callers that need streaming or conversation
 * attachment use build() and prompt the returned ConversationAgent themselves.
 */
class AgentExecutor
{
    public function __construct(
        private readonly UserAgent $userAgents,
        private readonly ToolResolver $tools,
        private readonly AgentReasoningService $reasoning,
    ) {}

    /**
     * Compile and run the agent for a single message, returning the final text.
     *
     * @param  array<string, mixed>  $context
     */
    public function execute(Agent $agent, string $message, array $context = []): string
    {
        $definition = $this->compile($agent, $message, $context);

        $response = $this->build($definition)->prompt(
            $message,
            provider: $this->resolveProvider($definition->provider),
            model: $definition->model,
        );

        return (string) $response;
    }

    /**
     * Compile the agent into a definition, drafting a plan first when planning
     * is enabled (the plan is folded into the system prompt and surfaced back
     * to callers via $context so they can persist it).
     *
     * @param  array<string, mixed>  $context
     */
    public function compile(Agent $agent, string $message, array $context = []): AgentDefinition
    {
        $agent->loadMissing(['workspace', 'toolConfigs', 'skills.references', 'skills.scripts', 'knowledge', 'memories']);

        $plan = $context['plan'] ?? $this->plan($agent, $message, $context);

        return $this->userAgents->compile($agent, $message, $context, $plan);
    }

    /**
     * Materialise a definition into the promptable runtime agent.
     */
    public function build(AgentDefinition $definition): ConversationAgent
    {
        return new ConversationAgent($definition->systemPrompt, $definition->tools);
    }

    /**
     * Draft a plan for the message without executing — used by streaming
     * callers that persist the plan on the run before prompting.
     *
     * @param  array<string, mixed>  $context
     * @return array{goal: string, needs_tools: bool, steps: list<array{title: string, detail?: string}>}|null
     */
    public function plan(Agent $agent, string $message, array $context = []): ?array
    {
        $agent->loadMissing(['toolConfigs', 'skills.scripts']);

        $toolNames = $this->tools->names($this->tools->resolve($agent, $context));

        return $this->reasoning->plan($agent, $message, $toolNames, [
            'workspace_id' => $agent->workspace_id,
            'parent_run_id' => $context['agent_run_id'] ?? null,
        ]);
    }

    public function resolveProvider(?string $provider): ?Lab
    {
        return $provider === null ? null : Lab::tryFrom($provider);
    }
}
