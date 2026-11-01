<?php

namespace App\Ai\Tools\WorkflowBuilder;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * How `WorkflowBuilderTitleAgent` hands back a title (see `ToolSubmission`).
 */
class SubmitWorkflowTitleTool implements Tool
{
    public const NAME = 'submit_workflow_title';

    public function name(): string
    {
        return self::NAME;
    }

    public function description(): Stringable|string
    {
        return 'Submits the workflow title.';
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
            'title' => $schema->string()->description('3 to 6 words, Title Case, saying what the workflow does.')->required(),
        ];
    }
}
