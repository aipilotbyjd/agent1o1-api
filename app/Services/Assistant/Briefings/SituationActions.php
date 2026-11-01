<?php

namespace App\Services\Assistant\Briefings;

use App\Enums\Assistant\AssistantSessionOrigin;
use App\Enums\Assistant\AssistantSituationStatus;
use App\Models\Assistant\AssistantSituation;
use App\Models\Assistant\AssistantSituationStep;
use App\Services\Assistant\Runtime\AssistantLoop;

/**
 * What the owner can do with a Situation: hand it to the assistant (it
 * becomes a conversation that works through the steps), tick off steps, or
 * dismiss it.
 */
class SituationActions
{
    public function __construct(private readonly AssistantLoop $loop) {}

    public function send(AssistantSituation $situation): AssistantSituation
    {
        if ($situation->assistant_session_id !== null) {
            return $situation;
        }

        $session = $situation->assistant->sessions()->create([
            'title' => $situation->title,
            'origin' => AssistantSessionOrigin::Task,
            'last_activity_at' => now(),
        ]);

        $situation->forceFill(['status' => AssistantSituationStatus::Sent, 'assistant_session_id' => $session->id])->save();

        $this->loop->send($session, $this->brief($situation));

        return $situation->refresh();
    }

    private function brief(AssistantSituation $situation): string
    {
        $steps = $situation->steps
            ->where('status', '!=', 'skipped')
            ->values()
            ->map(fn (AssistantSituationStep $step, int $index): string => ($index + 1).'. '.$step->body)
            ->implode("\n");

        return trim(implode("\n\n", array_filter([
            "Please help me with this: {$situation->title}",
            $situation->summary,
            $situation->next_step ? "Suggested next step: {$situation->next_step}" : null,
            $steps !== '' ? "Steps:\n{$steps}" : null,
            'Work through it, asking me before anything that sends or changes something.',
        ])));
    }
}
