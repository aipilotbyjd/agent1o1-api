<?php

namespace App\Ai\Tools\WorkflowBuilder;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * How `WorkflowNodeConfigAgent` hands back a proposed config (see `ToolSubmission`).
 * The config travels as a JSON string, like `AddNodeTool`'s `config_json`,
 * since its shape differs per node type.
 */
class SubmitNodeConfigTool implements Tool
{
    public const NAME = 'submit_node_config';

    public function name(): string
    {
        return self::NAME;
    }

    public function description(): Stringable|string
    {
        return 'Submits the proposed node config.';
    }

    public function handle(Request $request): Stringable|string
    {
        return 'Received.';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'config_json' => $schema->string()->description("The node's config as a JSON object string, matching its config schema.")->required(),
            'explanation' => $schema->string()->description('One or two sentences on what this config does.')->required(),
            'needs_from_user' => $schema->array()->items($schema->string())->description('Anything the user must still supply themselves: connected accounts, secrets, IDs only they know.')->required(),
        ];
    }
}
