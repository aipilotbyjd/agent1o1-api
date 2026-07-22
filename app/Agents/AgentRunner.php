<?php

namespace App\Agents;

use App\Agents\Engine\AgentExecutor;
use App\Agents\User\ConversationAgent;
use App\Contracts\AgentRunnable;
use App\Models\Agent;

/**
 * Thin adapter kept for existing callers (jobs, tools, evals). All real work
 * now lives in the engine: App\Agents\Engine\AgentExecutor compiles and runs
 * definitions produced by App\Agents\User\UserAgent.
 */
class AgentRunner implements AgentRunnable
{
    public function __construct(private readonly AgentExecutor $executor) {}

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

        return $this->executor->execute($agent, $message, $context);
    }

    /**
     * Compile a runnable agent (system prompt + tools) from a model record.
     *
     * Exposed so interactive callers (conversations, streaming) can attach a
     * conversation participant before prompting.
     *
     * @param  array<string, mixed>  $context
     */
    public function build(Agent $agent, string $message, array $context = []): ConversationAgent
    {
        return $this->executor->build($this->executor->compile($agent, $message, $context));
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
        return $this->executor->plan($agent, $message, $context);
    }
}
