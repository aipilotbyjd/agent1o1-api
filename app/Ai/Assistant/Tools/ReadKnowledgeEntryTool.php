<?php

namespace App\Ai\Assistant\Tools;

use App\Enums\Assistant\AssistantToolEffect;
use App\Models\Assistant\Assistant;
use App\Services\Agents\KnowledgeBase;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * The full text of one knowledge-base document the owner can see.
 */
class ReadKnowledgeEntryTool extends AssistantTool
{
    public const string NAME = 'read_knowledge_document';

    private const int MAX_CHARS = 30000;

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
        return 'Reads a whole knowledge-base document by the source name from search_knowledge, when a snippet is not enough.';
    }

    protected function execute(Request $request): string
    {
        $text = $this->knowledge->readDocument($this->assistant->workspace, (string) $request['source'], $request['collection'] ?? null, $this->assistant->user);

        return $text === null ? 'No document has that source name. Search again to find it.' : Str::limit($text, self::MAX_CHARS);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'source' => $schema->string()->required(),
            'collection' => $schema->string(),
        ];
    }
}
