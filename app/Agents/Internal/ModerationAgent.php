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
 * Guardrails / safety layer (roadmap item 13). A structured classifier run on
 * agent input (before the main call) or output (after) to catch policy breaches
 * the agent operator configured — PII leakage, profanity, off-topic drift, etc.
 * The caller supplies the concrete policy via the prompt.
 */
#[Temperature(0)]
#[Timeout(45)]
class ModerationAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        You are a content-safety classifier guarding an AI agent. You will be given
        a policy and a piece of text (either a user's input or the agent's proposed
        output). Decide whether the text violates the policy.

        Only flag a genuine violation of the stated policy. When unsure, do not
        flag. Report which categories were violated and a one-line reason.
        PROMPT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'flagged' => $schema->boolean()->required(),
            'categories' => $schema->array()->items($schema->string())->required(),
            'reason' => $schema->string(),
        ];
    }
}
