<?php

namespace App\Services\Assistant\Tools;

use App\Ai\Assistant\Tools\ReadKnowledgeEntryTool;
use App\Ai\Assistant\Tools\SaveToKnowledgeTool;
use App\Ai\Assistant\Tools\SearchKnowledgeBaseTool;
use App\Contracts\Assistant\ProvidesAssistantTools;
use App\Models\Assistant\Assistant;
use App\Models\Assistant\AssistantSession;
use App\Services\Agents\Knowledge\KnowledgeSources;
use App\Services\Agents\KnowledgeBase;

/**
 * The knowledge base (the Brain) as the owner's assistant sees it.
 * Incognito conversations can search it but not add to it.
 */
class KnowledgeToolProvider implements ProvidesAssistantTools
{
    public function __construct(
        private readonly KnowledgeBase $knowledge,
        private readonly KnowledgeSources $sources,
    ) {}

    public function toolsFor(Assistant $assistant, AssistantSession $session): array
    {
        return array_values(array_filter([
            new SearchKnowledgeBaseTool($assistant, $this->knowledge),
            new ReadKnowledgeEntryTool($assistant, $this->knowledge),
            $session->incognito ? null : new SaveToKnowledgeTool($assistant, $this->knowledge, $this->sources),
        ]));
    }
}
