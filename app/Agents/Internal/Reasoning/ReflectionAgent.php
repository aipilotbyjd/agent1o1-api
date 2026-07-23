<?php

namespace App\Agents\Internal\Reasoning;

use App\Agents\Internal\InternalAgent;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Stringable;

/**
 * Reflection / self-correction loop (roadmap item 2). After a draft answer (or a
 * batch of tool calls) the agent critiques its own work: is the result actually
 * grounded in what the tools returned, did it hallucinate, did it stop early?
 * The verdict feeds back into the executor as a correction nudge.
 */
#[Temperature(0.1)]
#[Timeout(60)]
class ReflectionAgent extends InternalAgent implements HasStructuredOutput
{
    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        You are a critical reviewer of another agent's work. You are given the
        original request, the plan, the tool results gathered so far, and the
        agent's current draft answer.

        Judge the draft honestly:
        - Is every claim supported by the tool results / provided context, or did
          the agent invent facts?
        - Did it actually finish the request, or stop early?
        - Are there obvious errors, wrong data, or skipped steps?

        Be strict but fair. If the draft is genuinely good, approve it — do not
        invent problems. If it needs work, say precisely what to fix in one or two
        actionable sentences.
        PROMPT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'approved' => $schema->boolean()->description('True when the draft is correct and complete enough to send.')->required(),
            'confidence' => $schema->number()->min(0)->max(1)->required(),
            'critique' => $schema->string()->description('What is wrong and how to fix it. Empty when approved.'),
        ];
    }
}
