<?php

namespace App\Agents\Internal\Memory;

use App\Agents\Internal\InternalAgent;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Stringable;

/**
 * Automatic memory extraction (roadmap item 4). At the end of a run this reads
 * the exchange and proposes durable facts worth remembering across future
 * conversations — stable preferences, identities, commitments — while ignoring
 * transient chatter.
 */
#[Temperature(0.1)]
#[Timeout(60)]
class MemoryExtractionAgent extends InternalAgent implements HasStructuredOutput
{
    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        You extract long-term memories from a conversation between a user and an
        assistant. Return only facts that will still be true and useful in future,
        unrelated conversations.

        Remember: stable preferences, the user's identity/role, ongoing projects,
        commitments the assistant made, durable configuration.
        Ignore: greetings, one-off questions, anything already obvious, transient
        state, and the assistant's own reasoning.

        Each memory is a short `key` (snake_case topic) and a concise `value`. If
        there is nothing worth keeping, return an empty list. Never fabricate.
        PROMPT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'memories' => $schema->array()->items(
                $schema->object([
                    'key' => $schema->string()->required(),
                    'value' => $schema->string()->required(),
                    'type' => $schema->string()->description('One of: fact, preference, identity, commitment.'),
                ])
            )->required(),
        ];
    }
}
