<?php

namespace App\Agents\Internal;

use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Stringable;

#[Temperature(0.5)]
class SummarizerAgent implements Agent
{
    use Promptable;

    public function __construct(
        private string $format = 'paragraph',
        private int $maxLength = 200,
    ) {}

    public function instructions(): Stringable|string
    {
        $formatInstruction = $this->format === 'bullets'
            ? 'Use bullet points.'
            : 'Write as a single paragraph.';

        return "You are a summarizer. Summarize the given text within {$this->maxLength} words. {$formatInstruction} Respond with only the summary, no additional text.";
    }
}
