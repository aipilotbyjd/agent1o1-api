<?php

namespace App\Ai\Assistant;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

/**
 * Writes the Daily report from what the collectors read: a two-sentence
 * summary, a catch-up with every fact attributed to its app, and the few
 * Situations that are real work the owner owns.
 */
class BriefingWriterAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(private readonly string $instructions) {}

    public function instructions(): string
    {
        return $this->instructions;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'summary' => $schema->string()->required(),
            'catch_up' => $schema->string()->required(),
            'situations' => $schema->array()->items($schema->object(fn (JsonSchema $schema): array => [
                'title' => $schema->string()->required(),
                'summary' => $schema->string()->required(),
                'next_step' => $schema->string(),
                'sources' => $schema->array()->items($schema->string()),
                'steps' => $schema->array()->items($schema->string()),
            ]))->required(),
        ];
    }
}
