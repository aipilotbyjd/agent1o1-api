<?php

namespace App\Ai\Assistant;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

/**
 * Reads the owner's feedback on one reply and decides whether it says
 * something lasting about how they like to be answered, and if so, writes
 * the updated Tone or Design notes.
 */
class StyleLearnerAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'PROMPT'
            You maintain two short sets of notes about how a person likes their AI assistant to answer:
            "tone": how it writes (length, formality, language, emoji, directness);
            "design": how answers are laid out (bullets, tables, headings, what comes first).
            You get the current notes, one reply the assistant wrote, and the person's feedback on it.
            Decide whether the feedback states a lasting preference about future answers. Complaints about facts, a single mistake, or a one-off request are NOT lasting preferences.
            If it is a lasting preference, return change=true, which notes it belongs to, and the complete updated notes: keep every existing point unless the feedback contradicts it, add the new preference as one short bullet, and stay under 15 bullets.
            Otherwise return change=false with kind "none" and empty notes.
            PROMPT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'change' => $schema->boolean()->required(),
            'kind' => $schema->string()->enum(['tone', 'design', 'none'])->required(),
            'notes' => $schema->string()->required(),
            'reason' => $schema->string()->required(),
        ];
    }
}
