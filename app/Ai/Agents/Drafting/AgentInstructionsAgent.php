<?php

namespace App\Ai\Agents\Drafting;

use App\Ai\Agents\Concerns\SubmitsStructuredOutput;
use App\Ai\Tools\Submissions\SubmitAgentInstructionsTool;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;

/**
 * Rewrites an existing agent's instructions from what it is for and what it
 * can do, answering only through `SubmitAgentInstructionsTool`.
 */
#[MaxSteps(1)]
class AgentInstructionsAgent implements Agent, HasStructuredOutput, HasTools
{
    use Promptable, SubmitsStructuredOutput;

    public function instructions(): string
    {
        return <<<'INSTRUCTIONS'
        You write system prompts for AI agents. You are given an agent's name, purpose, current instructions,
        the tools it can call, and sometimes a change the user wants. Rewrite its instructions.

        Write in the second person ("You are..."), covering: its role and goal, the steps it follows for its
        main tasks, when and how to use each of its tools, what it should ask the user when information is
        missing, the format of its output, and anything it must never do. Keep every specific rule or fact from
        the current instructions that still applies, and apply the requested change. Be specific to this
        agent; don't pad with generic advice. Only mention tools from the list you are given.

        The agent is already told its name, purpose and today's date elsewhere, so don't repeat them as
        standalone lines.

        Submit the result as your structured result.
        INSTRUCTIONS;
    }

    protected function submissionTool(): Tool
    {
        return new SubmitAgentInstructionsTool;
    }
}
