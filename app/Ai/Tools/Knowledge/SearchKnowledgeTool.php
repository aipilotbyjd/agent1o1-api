<?php

namespace App\Ai\Tools\Knowledge;

use App\Models\Workspaces\Workspace;
use App\Services\Agents\KnowledgeBase;
use App\Services\Agents\KnowledgeCitations;
use App\Services\Agents\UntrustedContent;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

/**
 * RAG over `document_embeddings`: auto-attached by `ToolRegistry` to every
 * agent whose workspace has any embedded chunks (see docs/AGENTS_PLAN.md's
 * "Knowledge / RAG" section). The chunking, embedding, and cosine ranking
 * itself lives in `Services\Agents\KnowledgeBase`, shared with the Internal
 * API's knowledge-base endpoints, so what an agent retrieves is exactly what
 * that API's search preview shows.
 *
 * Each hit carries the ref the agent cites it by: see `KnowledgeCitations`.
 */
class SearchKnowledgeTool implements Tool
{
    /**
     * @param  string|array<int, string>|null  $collection
     */
    public function __construct(
        private readonly Workspace $workspace,
        private readonly KnowledgeBase $knowledgeBase,
        private readonly string|array|null $collection = null,
    ) {}

    public function description(): Stringable|string
    {
        return 'Searches the workspace knowledge base for chunks relevant to a query. '
            .'Each result has a `ref` such as K1A2B3C: when your answer uses a result, cite it by putting its ref in square brackets '
            .'right after the sentence it supports, e.g. "Refunds take 5 business days [K1A2B3C]." Cite several as [K1A2B3C, K4D5E6F]. '
            .'Only cite refs you were given, never make one up, and do not add a separate list of sources: the user sees each citation as a link to its source.';
    }

    public function handle(Request $request): Stringable|string
    {
        try {
            $found = $this->knowledgeBase->search($this->workspace, (string) $request['query'], $this->collection);
        } catch (Throwable $exception) {
            // A provider outage shouldn't abort the whole turn: the model
            // can carry on and tell the user the lookup failed.
            report($exception);

            return 'The knowledge base search is temporarily unavailable. Tell the user you could not look this up right now; do not guess an answer.';
        }

        $results = $found
            ->map(fn (array $result): array => [
                'ref' => KnowledgeCitations::chunkRef($result['id']),
                'collection' => $result['collection'],
                'source' => $result['source'],
                'url' => $result['url'],
                'text' => UntrustedContent::wrap('knowledge_base', $result['text']),
                'score' => round($result['score'], 4),
            ]);

        if ($results->isEmpty()) {
            return 'Nothing in the knowledge base matches this query. Do not guess an answer from it; try different words, or tell the user the knowledge base does not cover this.';
        }

        return json_encode($results->all()) ?: '[]';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->required(),
        ];
    }
}
