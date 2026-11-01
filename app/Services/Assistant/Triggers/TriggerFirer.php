<?php

namespace App\Services\Assistant\Triggers;

use App\Enums\Assistant\AssistantSessionOrigin;
use App\Enums\Assistant\AssistantTriggerType;
use App\Enums\Assistant\AssistantTurnStatus;
use App\Models\Assistant\AssistantSession;
use App\Models\Assistant\AssistantTrigger;
use App\Models\Assistant\AssistantTurn;
use App\Services\Assistant\Runtime\AssistantLoop;
use Illuminate\Support\Str;

/**
 * Fires a trigger: a new conversation running its prompt (with a webhook's
 * payload filled in). Skipped while the trigger's previous run is still
 * going, so a slow run never piles up behind itself.
 */
class TriggerFirer
{
    public function __construct(private readonly AssistantLoop $loop) {}

    /**
     * @param  array<mixed>|null  $payload  a webhook's request data
     */
    public function fire(AssistantTrigger $trigger, ?array $payload = null): ?AssistantSession
    {
        if ($this->stillRunning($trigger)) {
            $this->advance($trigger);

            return null;
        }

        $session = $trigger->assistant->sessions()->create([
            'assistant_trigger_id' => $trigger->id,
            'title' => $trigger->name,
            'origin' => AssistantSessionOrigin::Trigger,
            'last_activity_at' => now(),
        ]);

        $this->advance($trigger);

        $this->loop->send($session, $this->render($trigger, $payload));

        return $session;
    }

    private function stillRunning(AssistantTrigger $trigger): bool
    {
        return AssistantTurn::query()
            ->whereIn('status', AssistantTurnStatus::inFlightValues())
            ->whereHas('session', fn ($query) => $query->where('assistant_trigger_id', $trigger->id))
            ->exists();
    }

    /**
     * Schedules move to their next time; a one-time trigger is used up.
     */
    private function advance(AssistantTrigger $trigger): void
    {
        $trigger->forceFill([
            'last_run_at' => now(),
            'next_run_at' => $trigger->type === AssistantTriggerType::Schedule ? $trigger->nextRunAfter(now()) : null,
        ])->save();

        if ($trigger->type === AssistantTriggerType::Once) {
            $trigger->delete();
        }
    }

    /**
     * `{{payload}}` in the prompt is replaced by the webhook's data; without
     * the placeholder the data is appended, so it is never silently lost.
     *
     * @param  array<mixed>|null  $payload
     */
    private function render(AssistantTrigger $trigger, ?array $payload): string
    {
        $prompt = "This run was started by your trigger \"{$trigger->name}\".\n\n{$trigger->prompt}";

        if ($payload === null) {
            return str_replace('{{payload}}', '(no data)', $prompt);
        }

        $data = Str::limit(
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}',
            (int) config('assistant.triggers.webhook_payload_max_chars'),
        );

        return str_contains($prompt, '{{payload}}')
            ? str_replace('{{payload}}', $data, $prompt)
            : "{$prompt}\n\nData received:\n{$data}";
    }
}
