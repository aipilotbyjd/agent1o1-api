<?php

namespace App\Ai\Tools\WorkflowBuilder;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * How `WorkflowExplanationAgent` hands back its explanation (see `ToolSubmission`).
 */
class SubmitWorkflowExplanationTool implements Tool
{
    public const NAME = 'submit_workflow_explanation';

    public function name(): string
    {
        return self::NAME;
    }

    public function description(): Stringable|string
    {
        return 'Submits the explanation of the workflow.';
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
            'summary' => $schema->string()->description('One to three plain-language sentences on what the workflow achieves.')->required(),
            'steps' => $schema->array()->items($schema->object([
                'key' => $schema->string()->description('The node key this step describes.')->required(),
                'description' => $schema->string()->description('What this node does, in plain language.')->required(),
            ]))->required(),
        ];
    }
}
