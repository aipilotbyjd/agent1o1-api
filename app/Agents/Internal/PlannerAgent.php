<?php

namespace App\Agents\Internal;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Planner half of the planner/executor split (roadmap item 1). Given the user's
 * request, the agent's own remit, and the tools it can call, it drafts an
 * ordered list of sub-goals the executor then works through. Kept deliberately
 * short — a plan is a scaffold, not the answer.
 */
#[Temperature(0.2)]
#[Timeout(60)]
class PlannerAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        You are a planning module for an autonomous agent. Given the agent's role,
        the tools it can use, and the user's request, break the work into an ordered
        list of concrete sub-goals.

        Rules:
        - 2 to 6 steps. Fewer is better. Trivial requests get a single step.
        - Each step is an outcome, not a keystroke ("Fetch the customer's open
          invoices", not "call the API").
        - Only reference tools that were listed as available.
        - If the request needs no tools and is a direct answer, return one step
          describing the answer to give.
        - Set `needs_tools` to false when the request can be answered directly from
          knowledge without calling any tool.
        PROMPT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'goal' => $schema->string()->description('One-sentence restatement of what success looks like.')->required(),
            'needs_tools' => $schema->boolean()->required(),
            'steps' => $schema->array()->items(
                $schema->object([
                    'title' => $schema->string()->required(),
                    'detail' => $schema->string(),
                ])
            )->required(),
        ];
    }
}
