<?php

namespace App\Ai\Tools\WorkflowBuilder;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * How `WorkflowImprovementAgent` hands back its suggestions (see `ToolSubmission`).
 */
class SubmitWorkflowImprovementsTool implements Tool
{
    public const NAME = 'submit_workflow_improvements';

    public const PRIORITIES = ['high', 'medium', 'low'];

    public function name(): string
    {
        return self::NAME;
    }

    public function description(): Stringable|string
    {
        return 'Submits the suggested improvements, most impactful first.';
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
            'improvements' => $schema->array()->items($schema->object([
                'title' => $schema->string()->description('A short imperative title, e.g. "Handle Slack failures".')->required(),
                'description' => $schema->string()->description('What to change and why, in one or two sentences.')->required(),
                'priority' => $schema->string()->enum(self::PRIORITIES)->required(),
                'node_keys' => $schema->array()->items($schema->string())->description('Keys of the existing nodes this concerns.'),
                'suggested_type' => $schema->string()->description('A node type from the catalog to add, if the fix needs one.'),
            ]))->required(),
        ];
    }
}
