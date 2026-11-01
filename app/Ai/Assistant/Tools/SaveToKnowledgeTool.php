<?php

namespace App\Ai\Assistant\Tools;

use App\Enums\Assistant\AssistantToolEffect;
use App\Models\Agents\DocumentEmbedding;
use App\Models\Assistant\Assistant;
use App\Services\Agents\Knowledge\KnowledgeSources;
use App\Services\Agents\KnowledgeBase;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Keeps reference material in the owner's private knowledge: a note, or a
 * web page that stays in sync. Saving a note under the same title again
 * replaces it.
 */
class SaveToKnowledgeTool extends AssistantTool
{
    public const string NAME = 'save_to_knowledge';

    public const string COLLECTION = 'personal';

    public function __construct(
        private readonly Assistant $assistant,
        private readonly KnowledgeBase $knowledge,
        private readonly KnowledgeSources $sources,
    ) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function effect(): AssistantToolEffect
    {
        return AssistantToolEffect::Internal;
    }

    public function description(): Stringable|string
    {
        return "Saves reference material to the person's private knowledge base so it can be searched later: a note (title + text) or a web page (title + url, kept in sync). "
            .'Use it when they ask you to keep notes, research, a document or a page for later. Saving a note with the same title replaces it. For short facts about the person use remember instead.';
    }

    protected function execute(Request $request): string
    {
        $title = trim((string) $request['title']);
        $url = trim((string) ($request['url'] ?? ''));
        $user = $this->assistant->user;

        if ($url !== '') {
            $this->sources->create($this->assistant->workspace, $user, ['type' => 'url', 'name' => $title, 'private' => true, 'config' => ['url' => $url]]);

            return "Saved the page \"{$title}\" to their knowledge base. It will be searchable in a moment.";
        }

        $text = trim((string) ($request['text'] ?? ''));

        if ($text === '') {
            return 'Give either text for a note or a url for a web page.';
        }

        DocumentEmbedding::query()
            ->where('workspace_id', $this->assistant->workspace_id)
            ->where('owner_id', $user->id)
            ->where('collection', self::COLLECTION)
            ->whereNull('knowledge_source_id')
            ->where('source', $title)
            ->delete();

        $this->knowledge->ingest($this->assistant->workspace, $text, $title, self::COLLECTION, null, ownerId: $user->id);

        return "Saved \"{$title}\" to their private knowledge base.";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->required(),
            'text' => $schema->string()->description('The note. Leave out when saving a web page.'),
            'url' => $schema->string()->description('A web page to keep. Leave out when saving a note.'),
        ];
    }
}
