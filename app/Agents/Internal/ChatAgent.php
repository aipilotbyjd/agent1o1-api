<?php

namespace App\Agents\Internal;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Stringable;

class ChatAgent implements Agent
{
    use Promptable;

    public function __construct(
        private string $systemPrompt = 'You are a helpful assistant.',
    ) {}

    public function instructions(): Stringable|string
    {
        return $this->systemPrompt;
    }
}
