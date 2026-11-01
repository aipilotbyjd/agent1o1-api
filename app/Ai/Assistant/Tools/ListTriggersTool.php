<?php

namespace App\Ai\Assistant\Tools;

use App\Enums\Assistant\AssistantToolEffect;
use App\Models\Assistant\Assistant;
use App\Models\Assistant\AssistantTrigger;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class ListTriggersTool extends AssistantTool
{
    public const string NAME = 'list_triggers';

    public function __construct(private readonly Assistant $assistant) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function effect(): AssistantToolEffect
    {
        return AssistantToolEffect::Read;
    }

    public function description(): Stringable|string
    {
        return 'Lists your triggers (schedules, one-time runs and webhooks) with their ids, status and next run.';
    }

    protected function execute(Request $request): string
    {
        $triggers = $this->assistant->triggers()->orderBy('name')->get();

        if ($triggers->isEmpty()) {
            return 'There are no triggers.';
        }

        return json_encode($triggers->map(fn (AssistantTrigger $trigger): array => [
            'id' => $trigger->id,
            'name' => $trigger->name,
            'type' => $trigger->type->value,
            'status' => $trigger->status->value,
            'schedule' => $trigger->cron,
            'timezone' => $trigger->timezone,
            'next_run' => $trigger->next_run_at?->tz($trigger->timezone)->toDayDateTimeString(),
            'prompt' => $trigger->prompt,
        ])->values()->all(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
