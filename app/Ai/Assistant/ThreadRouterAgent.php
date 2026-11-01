<?php

namespace App\Ai\Assistant;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

/**
 * Decides whether a text message continues the last SMS conversation or
 * starts a new one, when the gap alone doesn't settle it.
 */
class ThreadRouterAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return 'You route a new text message from a person to their AI assistant. Given the end of their last conversation and the new message, '
            .'answer whether the new message continues that conversation (a follow-up, a reply, a reference to it) or starts a new topic.';
    }

    public function schema(JsonSchema $schema): array
    {
        return ['continues' => $schema->boolean()->required()];
    }
}
