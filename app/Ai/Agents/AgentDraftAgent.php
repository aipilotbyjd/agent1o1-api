<?php

namespace App\Ai\Agents;

use App\Ai\Tools\SubmitAgentDraftTool;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Laravel\Ai\ToolChoice;

/**
 * Turns a plain-language request ("an agent that triages support tickets")
 * into a starting agent configuration, answering only through
 * `SubmitAgentDraftTool`.
 */
#[ToolChoice(ToolChoice::tool, SubmitAgentDraftTool::NAME)]
#[MaxSteps(1)]
class AgentDraftAgent implements Agent, HasTools
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'INSTRUCTIONS'
        You design AI agents. Given a user's description of the agent they want, draft its configuration.

        The instructions you write are the agent's system prompt. Write them in the second person ("You are..."),
        covering: its role and goal, the steps it follows for its main task, what it should ask the user when
        information is missing, the format of its output, and anything it must never do. Be specific to the
        user's request; don't pad with generic advice. Don't claim access to tools or data the user didn't mention.

        Pick the icon and color that best fit the agent's purpose.

        Submit the draft by calling the submit_agent_draft tool.
        INSTRUCTIONS;
    }

    public function tools(): iterable
    {
        return [new SubmitAgentDraftTool];
    }
}
