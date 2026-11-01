<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * How `EvalJudgeAgent` hands back its verdict (see `ToolSubmission`).
 */
class SubmitVerdictTool implements Tool
{
    public const NAME = 'submit_verdict';

    public function name(): string
    {
        return self::NAME;
    }

    public function description(): Stringable|string
    {
        return 'Submits whether the response satisfies the rubric.';
    }

    public function handle(Request $request): Stringable|string
    {
        return 'Received.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'passed' => $schema->boolean()->description('True only if the response fully satisfies the rubric.')->required(),
            'reason' => $schema->string()->description('One sentence explaining the verdict.')->required(),
        ];
    }
}
