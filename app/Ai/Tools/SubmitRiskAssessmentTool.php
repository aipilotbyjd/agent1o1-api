<?php

namespace App\Ai\Tools;

use App\Enums\Agents\ActionRisk;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * How `ActionReviewerAgent` hands back its assessment (see `ToolSubmission`).
 */
class SubmitRiskAssessmentTool implements Tool
{
    public const NAME = 'submit_risk_assessment';

    public function name(): string
    {
        return self::NAME;
    }

    public function description(): Stringable|string
    {
        return 'Submits how risky the proposed action is.';
    }

    public function handle(Request $request): Stringable|string
    {
        return 'Received.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'risk' => $schema->string()->enum(array_column(ActionRisk::cases(), 'value'))
                ->description('low only when the action is clearly what the user asked for and easy to undo.')->required(),
            'reason' => $schema->string()->description('One sentence a person deciding on the action can read.')->required(),
        ];
    }
}
