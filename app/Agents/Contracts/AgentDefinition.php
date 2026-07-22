<?php

namespace App\Agents\Contracts;

use Laravel\Ai\Contracts\Tool;

/**
 * The one shape every runnable agent — user-created or internal — compiles
 * down to before execution. The engine consumes definitions and never needs
 * to know where they came from.
 */
final readonly class AgentDefinition
{
    /**
     * @param  list<Tool>  $tools
     */
    public function __construct(
        public string $name,
        public AgentType $type,
        public string $systemPrompt,
        public ?string $provider,
        public ?string $model,
        public array $tools = [],
        public int $maxSteps = 15,
        public int $timeoutSeconds = 180,
    ) {}
}
