<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * How `AgentInstructionsAgent` hands back rewritten instructions (see `ToolSubmission`).
 */
class SubmitAgentInstructionsTool implements Tool
{
    public const NAME = 'submit_agent_instructions';

    public function name(): string
    {
        return self::NAME;
    }

    public function description(): Stringable|string
    {
        return 'Submits the rewritten agent instructions.';
    }

    public function handle(Request $request): Stringable|string
    {
        return 'Received.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'instructions' => $schema->string()->description('The complete rewritten system instructions.')->required(),
        ];
    }
}
