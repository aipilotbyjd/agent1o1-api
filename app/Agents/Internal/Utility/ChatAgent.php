<?php

namespace App\Agents\Internal\Utility;

use App\Agents\Internal\InternalAgent;
use Stringable;

class ChatAgent extends InternalAgent
{
    public function __construct(
        private string $systemPrompt = 'You are a helpful assistant.',
    ) {}

    public function instructions(): Stringable|string
    {
        return $this->systemPrompt;
    }
}
