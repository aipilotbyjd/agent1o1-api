<?php

namespace App\Ai\Tools\History;

use App\Models\Agents\Agent;
use App\Services\Agents\ConversationSearch;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

/**
 * Lets an agent look back through its own earlier conversations with the
 * person it's working for: "what did you find last week about X?". See
 * `ConversationSearch` for what is visible and how matches are ranked.
 * Pair with `ReadPastConversationTool` to read a match in context.
 */
class SearchPastConversationsTool implements Tool
{
    public const string NAME = 'search_past_conversations';

    public function __construct(
        private readonly Agent $agent,
        private readonly ConversationSearch $search,
        private readonly ?string $userId,
        private readonly ?string $currentSessionId,
        private readonly string $timezone,
    ) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function description(): Stringable|string
    {
        return 'Searches your earlier conversations with this user (not the current one) by keywords, '
            .'for when they refer to something you discussed, found or did before ("what did you find last week about the Acme deal?"). '
            .'Returns matching messages with a snippet, date and conversation_id, best first. '
            .'Use specific words likely to appear in the text (names, products, ticket numbers), and since/until to narrow it to a period. '
            .'Read a match in context with `'.ReadPastConversationTool::NAME.'`.';
    }

    public function handle(Request $request): Stringable|string
    {
        $query = trim((string) ($request['query'] ?? ''));

        if ($query === '') {
            return 'Nothing was searched: give some keywords to look for.';
        }

        $results = $this->search->search(
            $this->agent,
            $this->userId,
            $query,
            excludeSessionId: $this->currentSessionId,
            since: $this->date($request['since'] ?? null),
            until: $this->date($request['until'] ?? null, endOfDay: true),
            limit: (int) ($request['limit'] ?? ConversationSearch::DEFAULT_LIMIT),
            timezone: $this->timezone,
        );

        if ($results->isEmpty()) {
            return 'Nothing in your earlier conversations with this user matches. Try other words or a wider period, or tell the user you have no record of it.';
        }

        return json_encode([
            'results' => $results->all(),
            'note' => 'Snippets are short: read a conversation with `'.ReadPastConversationTool::NAME.'` before relying on details.',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Keywords to look for.')->required(),
            'since' => $schema->string()->description('Only messages on or after this date (YYYY-MM-DD).'),
            'until' => $schema->string()->description('Only messages on or before this date (YYYY-MM-DD).'),
            'limit' => $schema->integer()->description('How many matches to return, up to '.ConversationSearch::MAX_LIMIT.'. Defaults to '.ConversationSearch::DEFAULT_LIMIT.'.'),
        ];
    }

    /**
     * A date the model gave, on the person's own calendar.
     */
    private function date(mixed $value, bool $endOfDay = false): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            $date = Carbon::parse($value, $this->timezone);
        } catch (Throwable) {
            return null;
        }

        return ($endOfDay && ! str_contains($value, ':') ? $date->endOfDay() : $date)->utc();
    }
}
