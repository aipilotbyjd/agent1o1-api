<?php

namespace App\Services\Agents;

use App\Enums\Agents\AgentMessageRole;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentMessage;
use App\Models\Agents\AgentSession;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * An agent's own past conversations, searchable and readable by the agent
 * itself: "what did you find last week about X?". Backs
 * `SearchPastConversationsTool` and `ReadPastConversationTool`.
 *
 * Only conversations with the same person are visible: an agent is shared
 * across a workspace, and one member's chats are not another's to read.
 * Turns nobody started (a trigger, a follow-up) belong to the member they
 * ran for, so they're found by that member like their own chats.
 *
 * Matching is keyword based and runs in the database: a message matches when
 * it contains any of the query's words, and matches are ranked by how many
 * of the words they contain, a verbatim phrase, then recency. Only the most
 * recent `CANDIDATES` matches are ranked, which keeps a long history cheap
 * to search and favours what's recent, which is usually what's asked about.
 */
class ConversationSearch
{
    public const int DEFAULT_LIMIT = 8;

    public const int MAX_LIMIT = 20;

    private const int CANDIDATES = 400;

    private const int MAX_TERMS = 8;

    private const int SNIPPET_CHARS = 320;

    /** How much of one message a transcript read shows. */
    public const int MAX_MESSAGE_CHARS = 3000;

    /** How much one transcript read returns in total. */
    public const int MAX_TRANSCRIPT_CHARS = 24000;

    private const array STOP_WORDS = [
        'a', 'an', 'and', 'are', 'as', 'at', 'be', 'by', 'do', 'does', 'for', 'from', 'how', 'in', 'is', 'it',
        'me', 'my', 'of', 'on', 'or', 'our', 'that', 'the', 'this', 'to', 'was', 'we', 'what', 'when', 'where',
        'which', 'who', 'why', 'with', 'you', 'your', 'did', 'about', 'find', 'found', 'last', 'week',
    ];

    /**
     * The best-matching messages across this person's past conversations
     * with the agent, best first.
     *
     * @return Collection<int, array{conversation_id: string, conversation_title: string|null, message_id: string, role: string, date: string, snippet: string, score: float}>
     */
    public function search(
        Agent $agent,
        ?string $userId,
        string $query,
        ?string $excludeSessionId = null,
        ?CarbonInterface $since = null,
        ?CarbonInterface $until = null,
        int $limit = self::DEFAULT_LIMIT,
        string $timezone = 'UTC',
    ): Collection {
        $terms = $this->terms($query);

        if ($terms === []) {
            return collect();
        }

        $phrase = count($terms) > 1 ? mb_strtolower(Str::squish($query)) : null;

        $candidates = $this->messages($agent, $userId)
            ->with('session:id,title')
            ->when($excludeSessionId !== null, fn (Builder $builder) => $builder->where('agent_session_id', '!=', $excludeSessionId))
            ->when($since !== null, fn (Builder $builder) => $builder->where('created_at', '>=', $since))
            ->when($until !== null, fn (Builder $builder) => $builder->where('created_at', '<=', $until))
            ->where(function (Builder $builder) use ($terms): void {
                foreach ($terms as $term) {
                    $builder->orWhereRaw("lower(content) like ? escape '!'", ['%'.$this->escapeLike($term).'%']);
                }
            })
            ->latest('id')
            ->limit(self::CANDIDATES)
            ->get(['id', 'agent_session_id', 'role', 'content', 'created_at']);

        return $candidates
            ->map(function (AgentMessage $message) use ($terms, $phrase, $timezone): array {
                $text = mb_strtolower($message->content);
                $matched = array_values(array_filter($terms, fn (string $term): bool => str_contains($text, $term)));
                $score = count($matched) / count($terms) + ($phrase !== null && str_contains($text, $phrase) ? 0.5 : 0.0);

                return [
                    'conversation_id' => $message->agent_session_id,
                    'conversation_title' => $message->session?->title,
                    'message_id' => $message->id,
                    'role' => $message->role->value,
                    'date' => $message->created_at->copy()->tz($timezone)->format('D, M j, Y g:i A'),
                    'snippet' => $this->snippet($message->content, $matched[0] ?? $terms[0]),
                    'score' => round($score, 3),
                    'sort' => $message->id,
                ];
            })
            ->sortBy([['score', 'desc'], ['sort', 'desc']])
            ->take(max(1, min($limit, self::MAX_LIMIT)))
            ->map(fn (array $result): array => collect($result)->except('sort')->all())
            ->values();
    }

    /**
     * One of this person's conversations with the agent, oldest first, as
     * the agent can read it back: around one message when given, otherwise
     * the latest part, capped so a long conversation can't flood the
     * context.
     *
     * @return array{conversation_id: string, title: string|null, started: string, truncated: bool, messages: list<array{message_id: string, role: string, date: string, content: string}>}|null
     */
    public function read(Agent $agent, ?string $userId, string $sessionId, ?string $aroundMessageId = null, string $timezone = 'UTC'): ?array
    {
        $session = $this->sessions($agent, $userId)->find($sessionId);

        if ($session === null) {
            return null;
        }

        $messages = $session->messages()
            ->whereIn('role', [AgentMessageRole::User, AgentMessageRole::Assistant])
            ->when($aroundMessageId !== null, fn ($builder) => $builder->where('id', '>=', $this->windowStart($session, $aroundMessageId)))
            ->orderBy('id')
            ->get(['id', 'role', 'content', 'created_at']);

        $budget = self::MAX_TRANSCRIPT_CHARS;
        $kept = [];
        // Newest first when no anchor (the end of the conversation matters
        // most), oldest first from the anchor otherwise.
        $ordered = $aroundMessageId === null ? $messages->reverse()->values() : $messages;

        foreach ($ordered as $message) {
            $content = Str::limit((string) $message->content, self::MAX_MESSAGE_CHARS, '… [cut short]');

            if ($budget - mb_strlen($content) < 0) {
                break;
            }

            $budget -= mb_strlen($content);
            $kept[] = [
                'message_id' => $message->id,
                'role' => $message->role->value,
                'date' => $message->created_at->copy()->tz($timezone)->format('D, M j, Y g:i A'),
                'content' => $content,
            ];
        }

        if ($aroundMessageId === null) {
            $kept = array_reverse($kept);
        }

        return [
            'conversation_id' => $session->id,
            'title' => $session->title,
            'started' => $session->created_at->copy()->tz($timezone)->format('D, M j, Y g:i A'),
            'truncated' => count($kept) < $messages->count(),
            'messages' => $kept,
        ];
    }

    /**
     * Whether this person has any earlier conversation with the agent to
     * search, besides the current one.
     */
    public function hasHistory(Agent $agent, ?string $userId, ?string $excludeSessionId = null): bool
    {
        return $this->sessions($agent, $userId)
            ->when($excludeSessionId !== null, fn (Builder $builder) => $builder->whereKeyNot($excludeSessionId))
            ->exists();
    }

    /**
     * @return Builder<AgentSession>
     */
    private function sessions(Agent $agent, ?string $userId): Builder
    {
        return AgentSession::query()
            ->where('agent_id', $agent->id)
            ->where('workspace_id', $agent->workspace_id)
            ->when($userId === null, fn (Builder $builder) => $builder->whereNull('user_id'), fn (Builder $builder) => $builder->where('user_id', $userId));
    }

    /**
     * @return Builder<AgentMessage>
     */
    private function messages(Agent $agent, ?string $userId): Builder
    {
        return AgentMessage::query()
            ->whereIn('agent_session_id', $this->sessions($agent, $userId)->select('id'))
            ->whereIn('role', [AgentMessageRole::User, AgentMessageRole::Assistant])
            ->where('content', '!=', '');
    }

    /**
     * A few messages before the anchor, so a match is read with what led
     * up to it.
     */
    private function windowStart(AgentSession $session, string $aroundMessageId): string
    {
        return $session->messages()
            ->whereIn('role', [AgentMessageRole::User, AgentMessageRole::Assistant])
            ->where('id', '<=', $aroundMessageId)
            ->latest('id')
            ->limit(4)
            ->pluck('id')
            ->last() ?? $aroundMessageId;
    }

    /**
     * @return list<string>
     */
    private function terms(string $query): array
    {
        $words = preg_split('/[^\p{L}\p{N}_#.-]+/u', mb_strtolower($query), flags: PREG_SPLIT_NO_EMPTY) ?: [];

        return array_slice(array_values(array_unique(array_filter(
            array_map(fn (string $word): string => trim($word, '.-'), $words),
            fn (string $word): bool => mb_strlen($word) > 1 && ! in_array($word, self::STOP_WORDS, true),
        ))), 0, self::MAX_TERMS);
    }

    private function snippet(string $content, string $term): string
    {
        $content = Str::squish($content);
        $position = mb_stripos($content, $term);

        if ($position === false || mb_strlen($content) <= self::SNIPPET_CHARS) {
            return Str::limit($content, self::SNIPPET_CHARS);
        }

        $start = max(0, $position - intdiv(self::SNIPPET_CHARS, 3));
        $snippet = mb_substr($content, $start, self::SNIPPET_CHARS);

        return ($start > 0 ? '…' : '').$snippet.($start + self::SNIPPET_CHARS < mb_strlen($content) ? '…' : '');
    }

    /**
     * `!` rather than a backslash as the escape character: it means the same
     * on SQLite, MySQL and Postgres, where a backslash does not.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }
}
