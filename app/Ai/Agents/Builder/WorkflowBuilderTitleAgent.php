<?php

namespace App\Ai\Agents\Builder;

use App\Ai\Agents\Concerns\SubmitsStructuredOutput;
use App\Ai\Tools\WorkflowBuilder\SubmitWorkflowTitleTool;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;

/**
 * Names a builder session from the first thing the user asked for, so the
 * session list isn't a column of "Untitled workflow". Answers only through
 * `SubmitWorkflowTitleTool`.
 */
#[MaxSteps(1)]
class WorkflowBuilderTitleAgent implements Agent, HasStructuredOutput, HasTools
{
    use Promptable, SubmitsStructuredOutput;

    public function instructions(): string
    {
        return <<<'INSTRUCTIONS'
        Name a workflow automation from the user's description of it.

        Use 3 to 6 words in Title Case that say what the workflow does. Leave out filler words like
        "workflow", "automation" or "system". For example: "when a GitHub PR is merged, tell the team
        in Slack" becomes "GitHub PR Merge Alert"; "every morning email me yesterday's signups"
        becomes "Daily Signup Email".

        Submit the title as your structured result.
        INSTRUCTIONS;
    }

    protected function submissionTool(): Tool
    {
        return new SubmitWorkflowTitleTool;
    }
}
