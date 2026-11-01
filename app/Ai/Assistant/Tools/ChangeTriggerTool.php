<?php

namespace App\Ai\Assistant\Tools;

use App\Enums\Assistant\AssistantToolEffect;
use App\Models\Assistant\Assistant;
use App\Services\Assistant\Triggers\TriggerDefinitions;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Pauses, resumes or deletes one of the assistant's own triggers.
 */
class ChangeTriggerTool extends AssistantTool
{
    public const string NAME = 'change_trigger';

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
        return 'Pauses, resumes or deletes one of your triggers by id (see list_triggers).';
    }

    public function approvalReason(Request $request): string
    {
        return ucfirst((string) ($request['action'] ?? 'change')).' a trigger';
    }

    protected function execute(Request $request): string
    {
        $trigger = $this->assistant->triggers()->find((string) $request['id']);

        if ($trigger === null) {
            return 'No trigger with that id.';
        }

        return match ((string) $request['action']) {
            'pause' => tap("Paused \"{$trigger->name}\".", fn () => $this->definitions->update($trigger, ['status' => 'paused'])),
            'resume' => tap("Resumed \"{$trigger->name}\".", fn () => $this->definitions->update($trigger, ['status' => 'active'])),
            'delete' => tap("Deleted \"{$trigger->name}\".", fn () => $trigger->delete()),
            default => 'Unknown action. Use pause, resume or delete.',
        };
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'action' => $schema->string()->enum(['pause', 'resume', 'delete'])->required(),
        ];
    }
}
