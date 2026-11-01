<?php

namespace App\Ai\Assistant\Tools;

use App\Enums\Assistant\AssistantToolEffect;
use App\Models\Assistant\Assistant;
use App\Services\Agents\KnowledgeBase;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Searches the knowledge base as the owner sees it: their private
 * knowledge (their Brain) plus the workspace's shared knowledge.
 */
class SearchKnowledgeBaseTool extends AssistantTool
{
    public const string NAME = 'search_knowledge';

    private const int RESULTS = 6;

    public function __construct(
        private readonly Assistant $assistant,
        private readonly KnowledgeBase $knowledge,
    ) {}

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
        return "Searches the knowledge base: the person's own private notes, files and synced app content (Drive, mail, repos, channels) plus the workspace's shared knowledge. "
            .'Use it before answering questions about their work, projects, customers, policies or documents. '
            .'Pass a result\'s source to read_knowledge_document for the full text. Mention the source when you use a result.';
    }

    protected function execute(Request $request): string
    {
        $results = $this->knowledge
            ->search($this->assistant->workspace, (string) $request['query'], null, self::RESULTS, $this->assistant->user)
            ->map(fn (array $hit): array => [
                'source' => $hit['source'],
                'collection' => $hit['collection'],
                'private' => $hit['private'],
                'url' => $hit['url'],
                'text' => $hit['text'],
                'score' => round($hit['score'], 3),
            ]);

        if ($results->isEmpty()) {
            return 'Nothing in the knowledge base matches. Try other words, or say it isn\'t covered: don\'t guess.';
        }

        return json_encode($results->all(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]';
    }

    public function schema(JsonSchema $schema): array
    {
        return ['query' => $schema->string()->required()];
    }
}
