<?php

namespace App\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

/**
 * A throwaway agent for a single one-off prompt with caller-supplied
 * instructions: what `AskAiNode` (docs/NODES_CATALOG.md's "Ask AI" node)
 * prompts through, since a workflow node's instructions come from its
 * `config`, not a dedicated `Ai/Agents/*` class per node.
 */
class AdHocPromptAgent implements Agent
{
    use Promptable;

    public function __construct(private readonly string $agentInstructions) {}

    public function instructions(): string
    {
        return $this->agentInstructions;
    }
}
