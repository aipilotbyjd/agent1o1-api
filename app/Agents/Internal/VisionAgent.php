<?php

namespace App\Agents\Internal;

use Illuminate\Support\Stringable;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

class VisionAgent implements Agent
{
    use Promptable;

    public function __construct(
        private string $systemPrompt = 'You are a highly capable vision analysis assistant. Analyze the provided images and detail what you see according to the user prompt.'
    ) {}

    public function instructions(): Stringable|string
    {
        return $this->systemPrompt;
    }
}
