<?php

namespace App\Ai\Tools;

use App\Models\Workspaces\Workspace;
use App\Services\Agents\KnowledgeBase;
use App\Services\Agents\UntrustedContent;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * The companion to `SearchKnowledgeTool`: search returns ranked snippets, this
 * fetches one document's full text by the `source` a search hit reported,
 * for when a snippet isn't enough context. Attached under the same
 * conditions as `SearchKnowledgeTool`: see `ToolRegistry`.
 */
class ReadKnowledgeDocumentTool implements Tool
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
        return 'Fetches the full text of a specific knowledge base document by its source identifier '
            .'(the "source" field a Search Knowledge Base result reports), for when a search snippet '
            .'is not enough context.';
    }

    public function handle(Request $request): Stringable|string
    {
        $text = $this->knowledgeBase->readDocument(
            $this->workspace,
            (string) $request['source'],
            $this->collection,
        );

        return $text === null
            ? json_encode(['error' => 'No document found for that source.'])
            : UntrustedContent::wrap('knowledge_base', $text);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'source' => $schema->string()->required(),
        ];
    }
}
