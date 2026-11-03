<?php

namespace App\Services\Agents;

use App\Enums\Agents\AgentMessageRole;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentMessage;
use Illuminate\Support\Str;

/**
 * Inline source citations for agent replies.
 *
 * Every knowledge-base result an agent is handed carries a short ref:
 * `[K1A2B3C]` for a search hit (`SearchKnowledgeTool`), `[D1A2B3C]` for a
 * whole document (`ReadKnowledgeDocumentTool`, always-included knowledge in
 * the prompt). The agent is told to put the ref right after the sentence it
 * supports. When the reply is stored, each ref is resolved against what the
 * agent was actually handed (this reply's tool results, the conversation's
 * earlier ones, and its always-included documents) and the matches are
 * kept on the message as `citations`, numbered in the order they first
 * appear: the chat shows `[1]`, `[2]` with the source behind each.
 *
 * A ref that resolves to nothing the agent saw is a made-up citation: it is
 * left out of `citations`, so it never shows as a real source, and dropped
 * from the plain-text version of the reply (`plain()`).
 *
 * The markers stay in the stored text, so later turns replay the reply as
 * the agent wrote it and keep citing the same way.
 */
class KnowledgeCitations
{
    /** One marker: one or more refs in square brackets, `[K1A2B3C]` or `[K1A2B3C, D4E5F6A]`. */
    public const string MARKER = '/\[\s*([KD][0-9A-F]{6}(?:\s*[,;]\s*[KD][0-9A-F]{6})*)\s*\]/i';

    /** How many earlier replies in the conversation are searched for a ref. */
    private const int EARLIER_MESSAGES = 20;

    private const int SNIPPET_CHARS = 600;

    public function __construct(
        private readonly KnowledgeBase $knowledgeBase,
        private readonly SkillInjector $skillInjector,
    ) {}

    /**
     * The ref for one search hit.
     */
    public static function chunkRef(string $chunkId): string
    {
        return 'K'.strtoupper(substr(sha1($chunkId), 0, 6));
    }

    /**
     * The ref for one whole document, the same whichever way the agent got
     * it: a document is named by its source, or by its collection when it
     * has none.
     */
    public static function documentRef(string $collection, ?string $source): string
    {
        return 'D'.strtoupper(substr(sha1(filled($source) ? "source:{$source}" : "collection:{$collection}"), 0, 6));
    }

    /**
     * The refs a text cites, in the order they first appear.
     *
     * @return list<string>
     */
    public function refsIn(string $text): array
    {
        preg_match_all(self::MARKER, $text, $matches);

        return collect($matches[1] ?? [])
            ->flatMap(fn (string $group): array => preg_split('/\s*[,;]\s*/', strtoupper(trim($group))) ?: [])
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Resolves a stored reply's citations and keeps them on it.
     */
    public function attachTo(AgentMessage $message, ?Agent $agent = null): AgentMessage
    {
        $citations = $this->forMessage($message, $agent) ?: null;

        if ($message->citations !== $citations) {
            $message->forceFill(['citations' => $citations])->save();
        }

        return $message;
    }

    /**
     * A stored reply's citations, resolved against what it and the
     * conversation's earlier replies were handed.
     *
     * @return list<array{number: int, ref: string, kind: string, collection: string|null, source: string|null, url: string|null, snippet: string}>
     */
    public function forMessage(AgentMessage $message, ?Agent $agent = null): array
    {
        $text = (string) $message->content;

        if ($this->refsIn($text) === []) {
            return [];
        }

        $earlier = AgentMessage::query()
            ->where('agent_session_id', $message->agent_session_id)
            ->where('role', AgentMessageRole::Assistant)
            ->whereKeyNot($message->id)
            ->where('id', '<', $message->id)
            ->latest('id')
            ->limit(self::EARLIER_MESSAGES)
            ->get(['id', 'tool_results', 'citations']);

        $toolResults = [
            ...($message->tool_results ?? []),
            ...$earlier->flatMap(fn (AgentMessage $previous): array => $previous->tool_results ?? [])->all(),
        ];

        return $this->resolve($text, $toolResults, $earlier->flatMap(fn (AgentMessage $previous): array => $previous->citations ?? [])->all(), $agent);
    }

    /**
     * The citations of `$text`, numbered in order of first appearance.
     *
     * @param  array<int, array<string, mixed>>  $toolResults  in the stored `tool_results` shape
     * @param  array<int, array<string, mixed>>  $earlierCitations  already resolved for earlier replies
     * @return list<array{number: int, ref: string, kind: string, collection: string|null, source: string|null, url: string|null, snippet: string}>
     */
    public function resolve(string $text, array $toolResults, array $earlierCitations = [], ?Agent $agent = null): array
    {
        $refs = $this->refsIn($text);

        if ($refs === []) {
            return [];
        }

        $known = [];

        foreach ($toolResults as $result) {
            foreach ($this->candidatesIn($result['result'] ?? null) as $candidate) {
                $known[$candidate['ref']] ??= $candidate;
            }
        }

        foreach ($earlierCitations as $citation) {
            if (is_array($citation) && isset($citation['ref'])) {
                $known[$citation['ref']] ??= collect($citation)->except('number')->all();
            }
        }

        $unresolvedDocuments = array_filter($refs, fn (string $ref): bool => ! isset($known[$ref]) && str_starts_with($ref, 'D'));

        if ($unresolvedDocuments !== [] && $agent !== null) {
            $known += $this->alwaysIncluded($agent, $unresolvedDocuments);
        }

        $citations = [];

        foreach ($refs as $ref) {
            if (isset($known[$ref])) {
                $citations[] = ['number' => count($citations) + 1, ...$known[$ref]];
            }
        }

        return $citations;
    }

    /**
     * The reply as plain text, for anywhere that shows it without the chat's
     * citation chips: each known ref becomes its number, `[1]`, and a ref
     * that resolved to nothing is dropped.
     *
     * @param  array<int, array<string, mixed>>|null  $citations
     */
    public function plain(string $text, ?array $citations): string
    {
        $numbers = collect($citations ?? [])->mapWithKeys(fn (array $citation): array => [strtoupper((string) $citation['ref']) => (int) $citation['number']]);

        $plain = preg_replace_callback(self::MARKER, function (array $match) use ($numbers): string {
            $cited = collect(preg_split('/\s*[,;]\s*/', strtoupper(trim($match[1]))) ?: [])
                ->map(fn (string $ref): ?int => $numbers->get($ref))
                ->filter()
                ->unique()
                ->values();

            return $cited->isEmpty() ? "\0" : '['.$cited->implode('][').']';
        }, $text) ?? $text;

        // A dropped marker takes the space before it along, so "days
        // [KFAKE01]." reads "days.", not "days .".
        return (string) preg_replace('/[ \t]*\x00/', '', $plain);
    }

    /**
     * The citable results in one tool result: a list of search hits, a
     * `{results: [...]}` wrapper, or one document.
     *
     * @return list<array{ref: string, kind: string, collection: string|null, source: string|null, url: string|null, snippet: string}>
     */
    private function candidatesIn(mixed $result): array
    {
        if (! is_string($result) || ! str_contains($result, '"ref"')) {
            return [];
        }

        $decoded = json_decode($result, true);

        if (! is_array($decoded)) {
            return [];
        }

        $items = match (true) {
            array_is_list($decoded) => $decoded,
            isset($decoded['ref']) => [$decoded],
            default => is_array($decoded['results'] ?? null) ? $decoded['results'] : [],
        };

        $candidates = [];

        foreach ($items as $item) {
            if (! is_array($item) || ! is_string($item['ref'] ?? null) || preg_match('/^[KD][0-9A-F]{6}$/', $item['ref']) !== 1) {
                continue;
            }

            $candidates[] = [
                'ref' => $item['ref'],
                'kind' => str_starts_with($item['ref'], 'K') ? 'chunk' : 'document',
                'collection' => isset($item['collection']) ? (string) $item['collection'] : null,
                'source' => isset($item['source']) ? (string) $item['source'] : null,
                'url' => isset($item['url']) ? (string) $item['url'] : null,
                'snippet' => $this->snippet((string) ($item['text'] ?? '')),
            ];
        }

        return $candidates;
    }

    /**
     * The agent's always-included documents (`SkillInjector`) among the
     * given refs: they're in its prompt, with their refs, on every turn.
     *
     * @param  array<int, string>  $refs
     * @return array<string, array{ref: string, kind: string, collection: string|null, source: string|null, url: string|null, snippet: string}>
     */
    private function alwaysIncluded(Agent $agent, array $refs): array
    {
        $found = [];

        foreach ($this->skillInjector->alwaysIncludedKnowledge($agent)['included'] as $document) {
            $ref = self::documentRef($document['collection'], $document['source']);

            if (! in_array($ref, $refs, true)) {
                continue;
            }

            $found[$ref] = [
                'ref' => $ref,
                'kind' => 'document',
                'collection' => $document['collection'],
                'source' => $document['source'],
                'url' => null,
                'snippet' => $this->snippet($this->knowledgeBase->manualDocumentText($agent->workspace, $document['collection'], $document['source'])),
            ];
        }

        return $found;
    }

    /**
     * A source's text as shown behind a citation: without the untrusted
     * wrapper the agent was handed it in, and cut short.
     */
    private function snippet(string $text): string
    {
        $text = (string) preg_replace('#^\s*<'.UntrustedContent::TAG.'[^>]*>\s*|\s*</'.UntrustedContent::TAG.'>\s*$#', '', $text);

        return Str::limit(trim($text), self::SNIPPET_CHARS);
    }
}
