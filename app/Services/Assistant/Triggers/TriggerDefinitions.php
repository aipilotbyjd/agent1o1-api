<?php

namespace App\Services\Assistant\Triggers;

use App\Enums\Assistant\AssistantTriggerStatus;
use App\Enums\Assistant\AssistantTriggerType;
use App\Models\Assistant\Assistant;
use App\Models\Assistant\AssistantTrigger;
use App\Services\Timezones\Timezones;
use Cron\CronExpression;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Creating and changing triggers: shared by the settings screen and the
 * assistant's own tools, so both obey the same rules.
 */
class TriggerDefinitions
{
    /**
     * @param  array{type: string, name: string, prompt: string, cron?: string|null, timezone?: string|null, run_at?: string|null}  $data
     */
    public function create(Assistant $assistant, array $data, string $createdBy): AssistantTrigger
    {
        if ($assistant->triggers()->count() >= (int) config('assistant.triggers.max_per_assistant')) {
            throw ValidationException::withMessages(['type' => 'You can have up to '.config('assistant.triggers.max_per_assistant').' triggers.']);
        }

        $type = AssistantTriggerType::tryFrom($data['type']) ?? throw ValidationException::withMessages(['type' => 'Unknown trigger type.']);

        $trigger = new AssistantTrigger([
            'assistant_id' => $assistant->id,
            'type' => $type,
            'name' => Str::limit(trim($data['name']), 120, ''),
            'prompt' => trim($data['prompt']),
            'created_by' => $createdBy,
            'status' => AssistantTriggerStatus::Active,
            'timezone' => $assistant->user->timezoneIn($assistant->workspace),
        ]);

        $this->applySchedule($trigger, $data);

        if ($type === AssistantTriggerType::Webhook) {
            $trigger->webhook_token = Str::random(48);
        }

        $trigger->save();

        return $trigger;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(AssistantTrigger $trigger, array $data): AssistantTrigger
    {
        $trigger->fill(collect($data)->only(['name', 'prompt'])->all());

        if (array_key_exists('cron', $data) || array_key_exists('timezone', $data) || array_key_exists('run_at', $data)) {
            $this->applySchedule($trigger, [...$trigger->only(['cron', 'timezone']), ...$data]);
        }

        if (isset($data['status'])) {
            $trigger->status = AssistantTriggerStatus::from($data['status']);

            if ($trigger->status === AssistantTriggerStatus::Active) {
                // Back on: a fresh start, and no backlog of missed runs.
                $trigger->consecutive_failures = 0;
                $trigger->next_run_at = $trigger->type === AssistantTriggerType::Schedule ? $trigger->nextRunAfter(now()) : $trigger->next_run_at;
            }
        }

        $trigger->save();

        return $trigger;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applySchedule(AssistantTrigger $trigger, array $data): void
    {
        $timezone = filled($data['timezone'] ?? null)
            ? Timezones::canonical($data['timezone']) ?? throw ValidationException::withMessages(['timezone' => 'Pick a timezone from the list.'])
            : $trigger->timezone;

        $trigger->timezone = $timezone;

        if ($trigger->type === AssistantTriggerType::Schedule) {
            $cron = trim((string) ($data['cron'] ?? ''));

            if (! CronExpression::isValidExpression($cron)) {
                throw ValidationException::withMessages(['cron' => 'That schedule is not a valid cron expression (e.g. "0 9 * * 1-5" for 9:00 on weekdays).']);
            }

            $trigger->cron = $cron;
            $trigger->next_run_at = $trigger->nextRunAfter(now());
        }

        if ($trigger->type === AssistantTriggerType::Once) {
            $runAt = isset($data['run_at']) ? now()->parse((string) $data['run_at'], $timezone)->utc() : null;

            if ($runAt === null || $runAt->isPast()) {
                throw ValidationException::withMessages(['run_at' => 'Pick a time in the future.']);
            }

            $trigger->run_at = $runAt;
            $trigger->next_run_at = $runAt;
        }
    }
}
