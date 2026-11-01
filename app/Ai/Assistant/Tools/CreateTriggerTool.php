<?php

namespace App\Ai\Assistant\Tools;

use App\Enums\Assistant\AssistantToolEffect;
use App\Models\Assistant\Assistant;
use App\Services\Assistant\Triggers\TriggerDefinitions;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Lets the assistant set itself up to run later or on a schedule ("every
 * weekday at 9, summarise my unread email"). It asks the owner first, like
 * any change, and the owner can pause or delete the trigger any time.
 */
class CreateTriggerTool extends AssistantTool
{
    public const string NAME = 'create_trigger';

    public function __construct(
        private readonly Assistant $assistant,
        private readonly TriggerDefinitions $definitions,
    ) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function effect(): AssistantToolEffect
    {
        return AssistantToolEffect::Write;
    }

    public function description(): Stringable|string
    {
        return 'Creates a trigger that runs you on your own later: "schedule" repeats on a cron expression in the person\'s own timezone unless you pass an IANA timezone '
            .'(e.g. "0 9 * * 1-5" = 9:00 on weekdays), "once" runs a single time at run_at (ISO date-time), and "webhook" gives a URL other systems can call. '
            .'The prompt is what you will be asked to do each time, written as an instruction to yourself.';
    }

    public function approvalReason(Request $request): string
    {
        return 'Create a trigger: '.($request['name'] ?? 'unnamed');
    }

    protected function execute(Request $request): string
    {
        try {
            $trigger = $this->definitions->create($this->assistant, [
                'type' => (string) $request['type'],
                'name' => (string) $request['name'],
                'prompt' => (string) $request['prompt'],
                'cron' => $request['cron'] ?? null,
                'timezone' => $request['timezone'] ?? null,
                'run_at' => $request['run_at'] ?? null,
            ], 'assistant');
        } catch (ValidationException $e) {
            return 'Could not create the trigger: '.collect($e->errors())->flatten()->implode(' ');
        }

        return match ($trigger->type->value) {
            'webhook' => "Created webhook trigger \"{$trigger->name}\". Its URL: ".route('hooks.assistant', $trigger->webhook_token),
            default => "Created trigger \"{$trigger->name}\". Next run: {$trigger->next_run_at?->tz($trigger->timezone)->toDayDateTimeString()} ({$trigger->timezone}).",
        };
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->enum(['schedule', 'once', 'webhook'])->required(),
            'name' => $schema->string()->required(),
            'prompt' => $schema->string()->required(),
            'cron' => $schema->string(),
            'timezone' => $schema->string(),
            'run_at' => $schema->string(),
        ];
    }
}
