<?php

namespace App\Ai\Tools;

use App\Models\Agents\Agent;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * How `AgentDraftAgent` hands back a drafted agent (see `ToolSubmission`).
 */
class SubmitAgentDraftTool implements Tool
{
    public const NAME = 'submit_agent_draft';

    public function name(): string
    {
        return self::NAME;
    }

    public function description(): Stringable|string
    {
        return 'Submits the drafted agent configuration.';
    }

    public function handle(Request $request): Stringable|string
    {
        return 'Received.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('A short, specific agent name, at most 5 words.')->required(),
            'description' => $schema->string()->description('One sentence on what the agent does, for the agent list.')->required(),
            'instructions' => $schema->string()->description('The full system instructions the agent follows.')->required(),
            'icon' => $schema->string()->enum(Agent::ICONS)->required(),
            'color' => $schema->string()->enum(Agent::COLORS)->required(),
        ];
    }
}
