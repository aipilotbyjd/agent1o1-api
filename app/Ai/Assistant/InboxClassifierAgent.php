<?php

namespace App\Ai\Assistant;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

/**
 * Labels one incoming email against the owner's plain-language label
 * definitions, and says whether it wants a reply from the owner.
 */
class InboxClassifierAgent implements Agent, HasStructuredOutput
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
            'labels' => $schema->array()->items($schema->object(fn (JsonSchema $schema): array => [
                'name' => $schema->string()->required(),
                'confidence' => $schema->number()->required(),
            ]))->required(),
            'needs_reply' => $schema->boolean()->required(),
        ];
    }
}
