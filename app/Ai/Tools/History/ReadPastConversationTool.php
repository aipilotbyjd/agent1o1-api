<?php

namespace App\Ai\Tools\History;

use App\Models\Agents\Agent;
use App\Services\Agents\ConversationSearch;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Reads one of the agent's earlier conversations with the same person, the
 * companion to `SearchPastConversationsTool`: a match's snippet is rarely
 * enough to answer from, the exchange around it usually is.
 */
class ReadPastConversationTool implements Tool
{
    public const string NAME = 'read_past_conversation';

    public function __construct(
        private readonly Agent $agent,
        private readonly ConversationSearch $search,
        private readonly ?string $userId,
        private readonly string $timezone,
    ) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function description(): Stringable|string
    {
        return 'Reads one of your earlier conversations with this user by its conversation_id (from `'.SearchPastConversationsTool::NAME.'`). '
            .'Pass message_id to start just before that message; without it you get the latest part of the conversation.';
    }

    public function handle(Request $request): Stringable|string
    {
        $conversation = $this->search->read(
            $this->agent,
            $this->userId,
            trim((string) ($request['conversation_id'] ?? '')),
            filled($request['message_id'] ?? null) ? (string) $request['message_id'] : null,
            $this->timezone,
        );

        if ($conversation === null) {
            return 'No earlier conversation of yours with this user has that id.';
        }

        return json_encode($conversation, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'conversation_id' => $schema->string()->required(),
            'message_id' => $schema->string()->description('A message to read around, from a search result.'),
        ];
    }
}
