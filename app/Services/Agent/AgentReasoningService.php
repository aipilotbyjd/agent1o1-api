<?php

namespace App\Services\Agent;

use App\Agents\Internal\PlannerAgent;
use App\Agents\Internal\ReflectionAgent;
use App\Models\Agent;
use Throwable;

/**
 * The planner/executor split and reflection loop (roadmap items 1 & 2).
 *
 * This service owns the two "extra thinking" passes that wrap the main executor
 * loop in AgentRunner. Both are best-effort: a failure returns null so the run
 * proceeds exactly as it would have without the feature.
 */
class AgentReasoningService
{
    /**
     * Draft a plan for the request. Returns the structured plan, or null if
     * planning is disabled/failed.
     *
     * @param  list<string>  $toolNames
     * @return array{goal: string, needs_tools: bool, steps: list<array{title: string, detail?: string}>}|null
     */
    public function plan(Agent $agent, string $message, array $toolNames = []): ?array
    {
        if (! $agent->planning_enabled) {
            return null;
        }

        $toolList = $toolNames === []
            ? 'No tools are available; you must answer directly.'
            : '- '.implode("\n- ", $toolNames);

        $prompt = <<<PROMPT
        The agent's role:
        {$agent->instructions}

        Tools available to the agent:
        {$toolList}

        The user's request:
        {$message}
        PROMPT;

        try {
            $response = (new PlannerAgent)->prompt(
                $prompt,
                provider: $agent->provider,
                model: $agent->model,
            );
        } catch (Throwable) {
            return null;
        }

        $steps = [];
        foreach (($response['steps'] ?? []) as $step) {
            $title = trim((string) ($step['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $steps[] = array_filter([
                'title' => $title,
                'detail' => trim((string) ($step['detail'] ?? '')) ?: null,
            ], fn ($v) => $v !== null);
        }

        if ($steps === []) {
            return null;
        }

        return [
            'goal' => trim((string) ($response['goal'] ?? '')),
            'needs_tools' => (bool) ($response['needs_tools'] ?? true),
            'steps' => $steps,
        ];
    }

    /**
     * Render a plan as a system-prompt fragment the executor works against.
     *
     * @param  array{goal: string, needs_tools: bool, steps: list<array{title: string, detail?: string}>}  $plan
     */
    public function renderPlan(array $plan): string
    {
        $lines = ["\n\n---\n## Your plan for this task"];

        if (! empty($plan['goal'])) {
            $lines[] = "Goal: {$plan['goal']}";
        }

        $lines[] = "\nWork through these sub-goals in order, revising if a tool result contradicts an assumption:";

        foreach ($plan['steps'] as $i => $step) {
            $n = $i + 1;
            $line = "{$n}. {$step['title']}";
            if (! empty($step['detail'])) {
                $line .= " — {$step['detail']}";
            }
            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    /**
     * Critique a draft answer against the request and gathered context.
     *
     * @param  array{goal: string, needs_tools: bool, steps: list<array{title: string, detail?: string}>}|null  $plan
     * @return array{approved: bool, confidence: float, critique: string}|null
     */
    public function reflect(Agent $agent, string $request, ?array $plan, string $toolContext, string $draft): ?array
    {
        if (! $agent->reflection_enabled || trim($draft) === '') {
            return null;
        }

        $planText = $plan ? $this->renderPlan($plan) : '(no explicit plan)';
        $toolContext = trim($toolContext) === '' ? '(no tools were called)' : $toolContext;

        $prompt = <<<PROMPT
        Original request:
        {$request}

        Plan:
        {$planText}

        Tool results gathered:
        {$toolContext}

        The agent's draft answer:
        {$draft}
        PROMPT;

        try {
            $response = (new ReflectionAgent)->prompt(
                $prompt,
                provider: $agent->provider,
                model: $agent->model,
            );
        } catch (Throwable) {
            return null;
        }

        return [
            'approved' => (bool) ($response['approved'] ?? true),
            'confidence' => (float) ($response['confidence'] ?? 1.0),
            'critique' => trim((string) ($response['critique'] ?? '')),
        ];
    }
}
