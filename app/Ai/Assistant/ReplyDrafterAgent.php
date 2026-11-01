<?php

namespace App\Ai\Assistant;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

/**
 * Drafts the owner's reply to one email, in their voice, or declines when
 * there's nothing substantive to answer or it isn't confident.
 */
class ReplyDrafterAgent implements Agent, HasStructuredOutput
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
            'should_reply' => $schema->boolean()->required(),
            'confident' => $schema->boolean()->required(),
            'body' => $schema->string()->required(),
        ];
    }
}
