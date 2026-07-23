<?php

namespace App\Agents\Internal\Utility;

use App\Agents\Internal\InternalAgent;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Stringable;

#[Temperature(0.4)]
#[Timeout(60)]
class SkillGeneratorAgent extends InternalAgent implements HasStructuredOutput
{
    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        You turn a short natural-language description into a reusable "agent skill" —
        a packaged capability that can be attached to any AI agent.

        Generate:
        - name: a concise, Title Case name (2-5 words).
        - description: one sentence summarizing what the skill does.
        - category: exactly one of General, Research, Data, Communication, Automation, Development, Content.
        - instructions: detailed instructions written as if addressing the agent directly —
          what it should do, how, and any constraints — for it to follow whenever this skill is attached.
        PROMPT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->required(),
            'description' => $schema->string()->required(),
            'category' => $schema->string()->required(),
            'instructions' => $schema->string()->required(),
        ];
    }
}
