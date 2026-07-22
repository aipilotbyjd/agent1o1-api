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
 * LLM judge for the eval framework (roadmap item 9). Grades an agent's answer
 * against a natural-language rubric, returning a strict pass/fail plus a reason
 * so a failing eval report explains itself.
 */
#[Temperature(0)]
#[Timeout(45)]
class EvalJudgeAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        You grade an AI agent's response against a rubric. You are given the
        original input, the rubric (what a correct response must do), and the
        agent's actual response.

        Decide strictly whether the response satisfies the rubric. Do not be
        lenient — if it misses part of the rubric, it fails. Give a one-sentence
        reason for your verdict.
        PROMPT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'passed' => $schema->boolean()->required(),
            'reason' => $schema->string()->required(),
        ];
    }
}
