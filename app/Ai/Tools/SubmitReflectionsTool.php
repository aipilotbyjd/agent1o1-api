<?php

namespace App\Ai\Tools;

use App\Enums\Agents\ReflectionType;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * How `ReflectionReviewerAgent` hands back its findings (see `ToolSubmission`).
 */
class SubmitReflectionsTool implements Tool
{
    public const NAME = 'submit_reflections';

    public function name(): string
    {
        return self::NAME;
    }

    public function description(): Stringable|string
    {
        return 'Submits the recurring patterns found in the review. Submit an empty list if nothing qualifies.';
    }

    public function handle(Request $request): Stringable|string
    {
        return 'Received.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'reflections' => $schema->array()->items($schema->object([
                'type' => $schema->string()->enum(array_column(ReflectionType::cases(), 'value'))->required(),
                'title' => $schema->string()->description('Short title of the proposed improvement.')->required(),
                'rationale' => $schema->string()->description('Why the change is needed and what it will improve.')->required(),
                'confidence' => $schema->integer()->description('0-100.')->required(),
                'session_numbers' => $schema->array()->items($schema->integer())->description('The session numbers that show this pattern.')->required(),
                'proposed_prompt' => $schema->string()->description('The text to apply, as described for the chosen type.')->required(),
                'target_skill_id' => $schema->string()->nullable()->description('The existing skill id to update. Only for skill_fix.'),
            ]))->required(),
        ];
    }
}
