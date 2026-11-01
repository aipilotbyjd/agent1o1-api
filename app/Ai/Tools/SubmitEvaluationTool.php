<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * How `SessionEvalJudgeAgent` hands back its verdict (see `ToolSubmission`).
 */
class SubmitEvaluationTool implements Tool
{
    public const NAME = 'submit_evaluation';

    private const OUTCOMES = ['success', 'failure', 'unknown'];

    public function name(): string
    {
        return self::NAME;
    }

    public function description(): Stringable|string
    {
        return 'Submits the grading of the conversation transcript.';
    }

    public function handle(Request $request): Stringable|string
    {
        return 'Received.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'criteria_results' => $schema->array()->items($schema->object([
                'id' => $schema->string()->required(),
                'name' => $schema->string()->required(),
                'result' => $schema->string()->enum(self::OUTCOMES)->required(),
                'rationale' => $schema->string()->required(),
            ]))->description('One entry per configured criterion.')->required(),
            'tags' => $schema->array()->items($schema->string())->description('Names of the configured tags that apply.')->required(),
            'data_results' => $schema->array()->items($schema->object([
                'id' => $schema->string()->required(),
                'name' => $schema->string()->required(),
                'value' => $schema->union(['string', 'number', 'boolean', 'null'])->description('The extracted value, or null if the transcript does not contain it.')->required(),
            ]))->description('One entry per configured data point.')->required(),
            'sentiment' => $schema->string()->enum(['positive', 'neutral', 'negative'])->nullable()->description('Null when sentiment analysis is disabled.')->required(),
            'call_successful' => $schema->string()->enum(self::OUTCOMES)->required(),
            'summary' => $schema->string()->description('One or two sentences on what happened.')->required(),
        ];
    }
}
