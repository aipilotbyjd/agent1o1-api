<?php

namespace App\Agents\Internal\Utility;

use App\Agents\Internal\InternalAgent;
use Illuminate\Support\Stringable;

class VisionAgent extends InternalAgent
{
    public function __construct(
        private string $systemPrompt = 'You are a highly capable vision analysis assistant. Analyze the provided images and detail what you see according to the user prompt.'
    ) {}

    public function instructions(): Stringable|string
    {
        return $this->systemPrompt;
    }
}
