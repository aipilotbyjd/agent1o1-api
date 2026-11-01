<?php

namespace App\Ai\Tools\WorkflowBuilder;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * How `WorkflowNodeSuggestionAgent` hands back its suggestions (see `ToolSubmission`).
 */
class SubmitNodeSuggestionsTool implements Tool
{
    public const NAME = 'submit_node_suggestions';

    public function name(): string
    {
        return self::NAME;
    }

    public function description(): Stringable|string
    {
        return 'Submits the suggested next nodes, most useful first.';
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
            'suggestions' => $schema->array()->items($schema->object([
                'type' => $schema->string()->description('A node type from the catalog.')->required(),
                'reason' => $schema->string()->description('One sentence on why this node is a useful next step here.')->required(),
                'connect_from' => $schema->string()->description('The key of the existing node it should follow, if any.'),
            ]))->required(),
        ];
    }
}
