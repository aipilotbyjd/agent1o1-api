<?php

namespace App\Ai\Tools;

use App\Models\Agents\Agent;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Lets an agent save or update a durable fact about itself or the current
 * user across sessions: the write side of `AgentMemory`, complementing the
 * read side `SkillInjector` injects into every turn's instructions (see
 * docs/AGENTS_PLAN.md's `agent_memories` section). Auto-attached to every
 * agent by `ToolRegistry`, unlike `SearchKnowledgeTool` which is conditional
 * on the workspace having embedded chunks: there's no equivalent
 * precondition for a write tool.
 */
class RememberTool implements Tool
{
    public const NAME = 'remember';

    public function __construct(
        private readonly Agent $agent,
        private readonly ?string $userId = null,
        private readonly ?string $sessionId = null,
    ) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function description(): Stringable|string
    {
        return 'Saves or updates a durable fact you will recall in every future conversation with this user. '
            .'Use it whenever the user tells you something about themselves, their work or their preferences, or asks you to remember something: '
            .'their name, what to call them, their company, their ideal customer profile, a preferred tone. '
            .'Use a short, stable snake_case key (e.g. "preferred_name") so re-saving it updates the existing fact instead of duplicating it.';
    }

    public function handle(Request $request): Stringable|string
    {
        $key = (string) $request['key'];
        $value = (string) $request['value'];
        $type = (string) ($request['type'] ?? 'fact');

        $this->agent->memories()->updateOrCreate(
            ['user_id' => $this->userId, 'key' => $key],
            ['value' => $value, 'type' => $type, 'agent_session_id' => $this->sessionId],
        );

        return "Remembered {$key}.";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'key' => $schema->string()->required(),
            'value' => $schema->string()->required(),
            'type' => $schema->string(),
        ];
    }
}
