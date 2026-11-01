<?php

namespace App\Contracts\Assistant;

use App\Ai\Assistant\Tools\AssistantTool;
use App\Models\Assistant\Assistant;
use App\Models\Assistant\AssistantSession;

/**
 * A source of tools for the assistant (connectors, the sandbox, …). Tag an
 * implementation `assistant.tools` in a service provider and `ToolCatalog`
 * offers its tools, behind the owner's rules and approvals.
 */
interface ProvidesAssistantTools
{
    /**
     * @return list<AssistantTool>
     */
    public function toolsFor(Assistant $assistant, AssistantSession $session): array;
}
