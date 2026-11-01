<?php

namespace App\Services\Agents;

use App\Actions\Billing\DeductCreditsAction;
use App\Enums\Billing\CreditTransactionType;
use App\Models\Agents\DocumentEmbedding;
use App\Models\Agents\KnowledgeSource;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Ai\ByokProviderRegistrar;
use App\Services\Billing\CreditMeter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Embeddings;
use RuntimeException;

/**
 * The workspace knowledge base behind `document_embeddings`: turning text
 * into stored chunks, and ranking those chunks against a query. Shared by
 * the Internal API's knowledge-base endpoints and `Ai\Tools\SearchKnowledgeTool`
 * (the agent-facing side of the same data), so ingestion and retrieval agree
 * on chunking and scoring instead of each implementing their own.
 *
 * Ranking is cosine similarity computed in PHP rather than a native vector
 * query: `document_embeddings.embedding` is JSON, deliberately, for
 * sqlite/Postgres portability. See that migration's docblock and
 * docs/AGENTS_PLAN.md's "Knowledge / RAG" section for the follow-up path to
 * a native pgvector column.
 *
 * Search is hybrid: a chunk's score is its cosine similarity plus a bonus for
 * containing the query's words. Embeddings are good at meaning and poor at
 * exact tokens (an invoice number, a product SKU, a person's surname) so a
 * chunk that literally contains them is lifted above ones that are merely
 * about something similar. Chunks that are neither semantically close nor
 * share a word with the query are dropped instead of returned as the "best"
 * of nothing, so a model searching for something the knowledge base doesn't
 * cover gets no results rather than misleading ones.
 */
class KnowledgeBase
{
    public const int DEFAULT_TOP_N = 5;

    public function __construct(private readonly ByokProviderRegistrar $byok) {}

    /**
     * The cosine similarity below which a chunk with no word in common with
     * the query is treated as unrelated.
     */
    public const float MIN_SEMANTIC_SCORE = 0.25;

    /**
     * Added to the score for the share of query words a chunk contains.
     */
    private const float KEYWORD_WEIGHT = 0.4;

    /**
     * Added on top when a chunk contains a multi-word query verbatim.
     */
    private const float PHRASE_WEIGHT = 0.1;

    /**
     * Words too common to say anything about whether a chunk is relevant.
     */
    private const array STOP_WORDS = [
        'a', 'an', 'and', 'are', 'as', 'at', 'be', 'by', 'do', 'does', 'for', 'from', 'how', 'in', 'is', 'it',
        'me', 'my', 'of', 'on', 'or', 'our', 'that', 'the', 'this', 'to', 'was', 'we', 'what', 'when', 'where',
        'which', 'who', 'why', 'with', 'you', 'your',
    ];

    /**
     * Target size of one stored chunk. Chunks are split on paragraph, then
     * sentence, boundaries, so a chunk runs a little over or under this.
     */
    private const int CHUNK_CHARACTERS = 1000;

    /**
     * Inputs per embeddings call: providers cap how many strings one
     * request may carry, so a long document is embedded in batches.
     */
    private const int EMBED_BATCH = 64;

    /**
     * The most characters of one document `readDocument()` hands back. A
     * whole document goes straight into a model's context, so an enormous
     * one is cut off (with a notice) instead of exhausting it.
     */
    public const int MAX_DOCUMENT_CHARACTERS = 60000;

    /**
     * Split text into chunks, embed them, and store one row per chunk. The
     * embedding tokens are charged to `$workspace`: see `chargeForEmbeddings()`.
     * With `$ownerId` the chunks are private to that member (their Brain).
     * Embedding runs on the workspace's own provider key where it has one
     * (the owner's personal key for private chunks, else the team key) and
     * then costs no token credits.
     *
     * Embedding happens first and everything is written in one transaction,
     * so a provider failure or a billing failure never leaves a half-ingested
     * document behind. With `$replaceSource`, chunks already stored for the
     * same `$source` in the same `$collection` (and with the same owner) are
     * swapped for the new ones in that same transaction: re-ingesting a
     * revised document replaces it rather than duplicating it, and a failed
     * re-ingest keeps the old one.
     *
     * @param  array<string, mixed>|null  $metadata
     * @return Collection<int, DocumentEmbedding>
     */
    public function ingest(
        Workspace $workspace,
        string $text,
        ?string $source = null,
        string $collection = 'default',
        ?array $metadata = null,
        ?string $ownerId = null,
        ?string $knowledgeSourceId = null,
        ?string $externalId = null,
        bool $replaceSource = false,
        ?int $revision = null,
    ): Collection {
        $chunks = $this->chunk($text);

        if ($chunks === []) {
            return collect();
        }

        ['vectors' => $vectors, 'usage' => $usage] = $this->embed($chunks, $this->byok->embeddingsProvider($workspace->id, $ownerId));

        $store = function () use ($workspace, $chunks, $vectors, $usage, $source, $collection, $metadata, $ownerId, $knowledgeSourceId, $externalId, $replaceSource, $revision): Collection {
            if ($replaceSource && $source !== null) {
                if ($revision !== null && $this->hasNewerRevision($workspace, $source, $collection, $ownerId, $revision)) {
                    return collect();
                }

                $this->deleteDocument($workspace, $source, $collection, $ownerId);
            }

            $stored = collect($chunks)->map(fn (string $chunk, int $index) => DocumentEmbedding::create([
                'workspace_id' => $workspace->id,
                'owner_id' => $ownerId,
                'collection' => $collection,
                'knowledge_source_id' => $knowledgeSourceId,
                'external_id' => $externalId,
                'source' => $source,
                'chunk_index' => $index,
                'chunk_text' => $chunk,
                'embedding' => $vectors[$index],
                'metadata' => $metadata,
                'ingest_revision' => $revision,
            ]));

            $this->chargeForEmbeddings($workspace, $stored->first(), $usage);

            return $stored;
        };

        if ($revision === null || $source === null || ! $replaceSource) {
            return DB::transaction($store);
        }

        // Serialises same-source ingests so the revision check and the swap
        // can't interleave across workers.
        return Cache::lock('knowledge-ingest:'.sha1("{$workspace->id}|{$collection}|{$ownerId}|{$source}"), 120)
            ->block(60, fn (): Collection => DB::transaction($store));
    }

    /**
     * Whether the stored copy of this document came from a later upload than
     * `$revision`.
     */
    private function hasNewerRevision(Workspace $workspace, string $source, string $collection, ?string $ownerId, int $revision): bool
    {
        return $this->scopedTo($workspace, $collection)
            ->where('source', $source)
            ->when($ownerId !== null, fn (Builder $builder) => $builder->where('owner_id', $ownerId), fn (Builder $builder) => $builder->shared())
            ->where('ingest_revision', '>', $revision)
            ->exists();
    }

    /**
     * Embeds texts in provider-sized batches.
     *
     * @param  array<int, string>  $texts
     * @param  string|array<string, null>  $provider
     * @return array{vectors: array<int, array<int, float>>, usage: array{prompt_tokens: int, provider: string|null, model: string|null}}
     */
    private function embed(array $texts, string|array $provider): array
    {
        $vectors = [];
        $usage = ['prompt_tokens' => 0, 'provider' => null, 'model' => null];

        foreach (array_chunk(array_values($texts), self::EMBED_BATCH) as $batch) {
            $response = Embeddings::for($batch)->generate($provider);

            if (count($response->embeddings) !== count($batch)) {
                throw new RuntimeException(sprintf(
                    'The embeddings provider returned %d vectors for %d chunks.',
                    count($response->embeddings),
                    count($batch),
                ));
            }

            $vectors = [...$vectors, ...$response->embeddings];
            $usage = [
                'prompt_tokens' => $usage['prompt_tokens'] + $response->tokens,
                'provider' => $response->meta->provider,
                'model' => $response->meta->model,
            ];
        }

        return ['vectors' => $vectors, 'usage' => $usage];
    }

    /**
     * Removes every chunk of one document, returning how many were deleted.
     * Only shared chunks are touched unless `$ownerId` is given, in which case
     * only that member's private ones are: a document is never removed
     * across the shared/private line.
     *
     * @param  string|array<int, string>|null  $collection
     */
    public function deleteDocument(Workspace $workspace, string $source, string|array|null $collection = null, ?string $ownerId = null): int
    {
        return $this->scopedTo($workspace, $collection)
            ->where('source', $source)
            ->when($ownerId !== null, fn (Builder $builder) => $builder->where('owner_id', $ownerId), fn (Builder $builder) => $builder->shared())
            ->delete();
    }

    /**
     * Bills the ingestion's embedding tokens against its first stored chunk.
     * With overdraft: the provider has already been paid by the time the
     * chunks exist. Retrieval (`search()`) isn't charged: one short query's
     * embedding rounds to nothing, and an agent's search is already billed
     * as the tool call it is.
     *
     * @param  array{prompt_tokens: int, provider: string|null, model: string|null}  $usage
     */
    private function chargeForEmbeddings(Workspace $workspace, DocumentEmbedding $firstChunk, array $usage): void
    {
        $credits = app(CreditMeter::class)->costForEmbeddings($usage);

        if ($credits === 0) {
            return;
        }

        app(DeductCreditsAction::class)->execute(
            $workspace,
            CreditTransactionType::KnowledgeIngestion,
            $firstChunk->id,
            $credits,
            $firstChunk->source !== null ? 'Knowledge ingestion ('.Str::limit($firstChunk->source, 200).')' : 'Knowledge ingestion',
            allowOverdraft: true,
        );
    }

    /**
     * The top-scoring chunks for a query, highest first.
     *
     * @param  string|array<int, string>|null  $collection  One collection, a
     *                                                      list to search across (an agent's attached sources: see
     *                                                      `ToolRegistry`), or null for every collection in the workspace.
     * @param  User|null  $viewer  With no viewer only shared knowledge is searched (what agents see);
     *                             with one, their private knowledge too, or only it, when `$includeShared` is false.
     * @return Collection<int, array{id: string, collection: string, source: string|null, url: string|null, private: bool, text: string, score: float}>
     */
    public function search(
        Workspace $workspace,
        string $query,
        string|array|null $collection = null,
        int $topN = self::DEFAULT_TOP_N,
        ?User $viewer = null,
        bool $includeShared = true,
        bool $excludeArtifactCollections = false,
    ): Collection {
        $queryVector = Embeddings::for([$query])
            ->generate($this->byok->embeddingsProvider($workspace->id, $viewer?->id))
            ->embeddings[0] ?? [];

        // Chunks are read in batches and only the running top N is kept, so
        // memory stays flat however large the workspace's knowledge base is.
        $best = [];

        $chunks = $this->visible($this->scopedTo($workspace, $collection), $viewer, $includeShared)
            ->when($excludeArtifactCollections, fn (Builder $builder) => $builder->excludingArtifactCollections())
            ->select(['id', 'owner_id', 'collection', 'source', 'chunk_text', 'embedding', 'metadata'])
            ->lazyById(500);

        $terms = $this->terms($query);
        $patterns = array_map($this->termPattern(...), $terms);
        $phrase = count($terms) > 1 ? mb_strtolower(trim($query, " \t\n\r\0\x0B.?!,;:")) : null;
        $mismatched = 0;

        foreach ($chunks as $chunk) {
            $vector = $chunk->embedding ?? [];

            if ($queryVector !== [] && $vector !== [] && count($vector) !== count($queryVector)) {
                $mismatched++;
            }

            $semantic = $this->cosineSimilarity($queryVector, $vector);
            $keyword = $this->keywordCoverage($patterns, $chunk->chunk_text);

            if ($semantic < self::MIN_SEMANTIC_SCORE && $keyword === 0.0) {
                continue;
            }

            $phraseBonus = $phrase !== null && str_contains(mb_strtolower($chunk->chunk_text), $phrase) ? self::PHRASE_WEIGHT : 0.0;

            $best[] = [
                'id' => $chunk->id,
                'collection' => $chunk->collection,
                'source' => $chunk->source,
                'url' => $chunk->metadata['url'] ?? null,
                'private' => $chunk->owner_id !== null,
                'text' => $chunk->chunk_text,
                'score' => $semantic + self::KEYWORD_WEIGHT * $keyword + $phraseBonus,
            ];

            if (count($best) > $topN) {
                $best = $this->rank($best);
                array_pop($best);
            }
        }

        if ($mismatched > 0) {
            // A chunk embedded with a different model has a different vector
            // length and can only ever match on keywords: worth knowing when
            // search quality drops after changing the embeddings provider.
            Log::warning('Knowledge base search skipped semantic scoring for chunks with mismatched embedding dimensions.', [
                'workspace_id' => $workspace->id,
                'chunks' => $mismatched,
                'query_dimensions' => count($queryVector),
            ]);
        }

        return collect($this->rank($best))->values();
    }

    /**
     * Shared knowledge for no viewer; for a viewer, shared plus their own
     * private knowledge (or only theirs).
     *
     * @param  Builder<DocumentEmbedding>  $query
     * @return Builder<DocumentEmbedding>
     */
    private function visible(Builder $query, ?User $viewer, bool $includeShared = true): Builder
    {
        return match (true) {
            $viewer === null => $query->shared(),
            $includeShared => $query->visibleTo($viewer),
            default => $query->where('owner_id', $viewer->id),
        };
    }

    /**
     * Highest score first; `usort` is stable, so ties keep insertion (id)
     * order.
     *
     * @param  array<int, array{id: string, collection: string, source: string|null, url: string|null, private: bool, text: string, score: float}>  $results
     * @return array<int, array{id: string, collection: string, source: string|null, url: string|null, private: bool, text: string, score: float}>
     */
    private function rank(array $results): array
    {
        usort($results, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return $results;
    }

    /**
     * The full text of one "document": every chunk sharing a `source`
     * (and, when given, a `collection`), reassembled in reading order. If the
     * same `source` exists in several collections and none was asked for, the
     * first collection alphabetically wins rather than interleaving different
     * documents. Capped at `MAX_DOCUMENT_CHARACTERS`.
     * Backs `Ai\Tools\ReadKnowledgeDocumentTool`: a search hit's `text` is
     * a chunk-level snippet, and this is what a model calls when a snippet
     * alone isn't enough context.
     *
     * @param  string|array<int, string>|null  $collection
     */
    public function readDocument(Workspace $workspace, string $source, string|array|null $collection = null, ?User $viewer = null, bool $excludeArtifactCollections = false): ?string
    {
        $rows = $this->visible($this->scopedTo($workspace, $collection), $viewer)
            ->when($excludeArtifactCollections, fn (Builder $builder) => $builder->excludingArtifactCollections())
            ->where('source', $source)
            ->orderBy('collection')
            ->orderBy('chunk_index')
            ->orderBy('id')
            ->select(['collection', 'chunk_text'])
            ->cursor();

        $parts = [];
        $length = 0;
        $documentCollection = null;
        $truncated = false;

        foreach ($rows as $row) {
            $documentCollection ??= $row->collection;

            if ($row->collection !== $documentCollection) {
                break;
            }

            $parts[] = $row->chunk_text;
            $length += mb_strlen($row->chunk_text) + 2;

            if ($length > self::MAX_DOCUMENT_CHARACTERS) {
                $truncated = true;

                break;
            }
        }

        if ($parts === []) {
            return null;
        }

        $text = implode("\n\n", $parts);

        if (! $truncated) {
            return $text;
        }

        return mb_substr($text, 0, self::MAX_DOCUMENT_CHARACTERS)
            ."\n\n[Document truncated after ".number_format(self::MAX_DOCUMENT_CHARACTERS).' characters.]';
    }

    /**
     * The shared documents added by hand (typed or uploaded, not synced from
     * a `KnowledgeSource`) in `$collections`, with their length as
     * `manualDocumentText()` would return it, oldest first. Sized in the
     * database so an agent's prompt can be budgeted without reading every
     * chunk's text. Chunks stored without a `source` form one document per
     * collection.
     *
     * @param  array<int, string>  $collections
     * @return Collection<int, array{collection: string, source: string|null, characters: int}>
     */
    public function manualDocuments(Workspace $workspace, array $collections): Collection
    {
        if ($collections === []) {
            return collect();
        }

        return $this->scopedTo($workspace, $collections)
            ->shared()
            ->whereNull('knowledge_source_id')
            ->selectRaw('collection, source, SUM(LENGTH(chunk_text)) as text_length, COUNT(*) as chunks, MIN(created_at) as first_ingested_at')
            ->groupBy('collection', 'source')
            ->orderBy('first_ingested_at')
            ->orderBy('source')
            ->toBase()
            ->get()
            ->map(fn (object $row): array => [
                'collection' => $row->collection,
                'source' => $row->source,
                'characters' => (int) $row->text_length + 2 * ((int) $row->chunks - 1),
            ]);
    }

    /**
     * One hand-added shared document's full text, chunks joined in reading
     * order: the counterpart of `manualDocuments()`.
     */
    public function manualDocumentText(Workspace $workspace, string $collection, ?string $source): string
    {
        return $this->scopedTo($workspace, $collection)
            ->shared()
            ->whereNull('knowledge_source_id')
            ->when($source === null, fn (Builder $builder) => $builder->whereNull('source'), fn (Builder $builder) => $builder->where('source', $source))
            ->orderBy('created_at')
            ->orderBy('chunk_index')
            ->orderBy('id')
            ->pluck('chunk_text')
            ->implode("\n\n");
    }

    /**
     * Whether a shared collection is fed by a synced source (a web page or a
     * connected app). Its content comes from outside the workspace, so it is
     * only ever searched, never put in an agent's prompt as-is.
     */
    public function isSynced(Workspace $workspace, string $collection): bool
    {
        return KnowledgeSource::query()
            ->where('workspace_id', $workspace->id)
            ->whereNull('owner_id')
            ->where('collection', $collection)
            ->exists();
    }

    /**
     * @param  string|array<int, string>|null  $collection
     * @return Builder<DocumentEmbedding>
     */
    private function scopedTo(Workspace $workspace, string|array|null $collection): Builder
    {
        return DocumentEmbedding::query()
            ->where('workspace_id', $workspace->id)
            ->when($collection !== null, fn (Builder $builder) => is_array($collection)
                ? $builder->whereIn('collection', $collection)
                : $builder->where('collection', $collection));
    }

    /**
     * Split on blank lines first (a paragraph is the most meaningful unit to
     * keep whole), then pack paragraphs up to the target size, splitting any
     * single paragraph that overshoots on sentence boundaries.
     *
     * @return array<int, string>
     */
    public function chunk(string $text): array
    {
        $chunks = [];
        $current = '';

        foreach ($this->paragraphs($text) as $paragraph) {
            if ($current !== '' && mb_strlen($current) + mb_strlen($paragraph) + 2 > self::CHUNK_CHARACTERS) {
                $chunks[] = $current;
                $current = '';
            }

            $current = $current === '' ? $paragraph : "{$current}\n\n{$paragraph}";
        }

        if (trim($current) !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }

    /**
     * @return array<int, string>
     */
    private function paragraphs(string $text): array
    {
        $paragraphs = [];

        foreach (preg_split('/\n\s*\n/', trim($text)) ?: [] as $paragraph) {
            $paragraph = trim($paragraph);

            if ($paragraph === '') {
                continue;
            }

            if (mb_strlen($paragraph) <= self::CHUNK_CHARACTERS) {
                $paragraphs[] = $paragraph;

                continue;
            }

            $paragraphs = [...$paragraphs, ...$this->splitLongParagraph($paragraph)];
        }

        return $paragraphs;
    }

    /**
     * @return array<int, string>
     */
    private function splitLongParagraph(string $paragraph): array
    {
        $pieces = [];
        $current = '';

        // Sentence boundaries first; a "sentence" longer than the target
        // (minified JSON, a giant URL list) is hard-split so one pathological
        // line can't become a single enormous chunk.
        foreach (preg_split('/(?<=[.!?])\s+/', $paragraph) ?: [] as $sentence) {
            foreach (mb_str_split($sentence, self::CHUNK_CHARACTERS) as $piece) {
                if ($current !== '' && mb_strlen($current) + mb_strlen($piece) + 1 > self::CHUNK_CHARACTERS) {
                    $pieces[] = $current;
                    $current = '';
                }

                $current = $current === '' ? $piece : "{$current} {$piece}";
            }
        }

        if (trim($current) !== '') {
            $pieces[] = $current;
        }

        return $pieces;
    }

    /**
     * The distinct, meaningful words of a query, lowercased.
     *
     * @return array<int, string>
     */
    private function terms(string $query): array
    {
        $words = preg_split('/[^\p{L}\p{N}_-]+/u', mb_strtolower($query), flags: PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter(
            $words,
            fn (string $word): bool => mb_strlen($word) > 1 && ! in_array($word, self::STOP_WORDS, true),
        )));
    }

    /**
     * A pattern matching one query term as a whole word.
     */
    private function termPattern(string $term): string
    {
        return '/(?<![\p{L}\p{N}_-])'.preg_quote($term, '/').'(?![\p{L}\p{N}_-])/u';
    }

    /**
     * The share of the query's terms (as `termPattern()` regexes) that appear
     * in `$text` as whole words, from 0 to 1.
     *
     * @param  array<int, string>  $patterns
     */
    private function keywordCoverage(array $patterns, string $text): float
    {
        if ($patterns === []) {
            return 0.0;
        }

        $haystack = mb_strtolower($text);
        $found = 0;

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $haystack) === 1) {
                $found++;
            }
        }

        return $found / count($patterns);
    }

    /**
     * @param  array<int, float>  $a
     * @param  array<int, float>  $b
     */
    private function cosineSimilarity(array $a, array $b): float
    {
        if ($a === [] || $b === [] || count($a) !== count($b)) {
            return 0.0;
        }

        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        foreach ($a as $i => $value) {
            $dot += $value * $b[$i];
            $normA += $value ** 2;
            $normB += $b[$i] ** 2;
        }

        if ($normA === 0.0 || $normB === 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }
}
