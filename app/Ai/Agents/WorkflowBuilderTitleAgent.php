<?php

namespace App\Ai\Agents;

use App\Ai\Tools\WorkflowBuilder\SubmitWorkflowTitleTool;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Laravel\Ai\ToolChoice;

/**
 * Names a builder session from the first thing the user asked for, so the
 * session list isn't a column of "Untitled workflow". Answers only through
 * `SubmitWorkflowTitleTool`.
 */
#[ToolChoice(ToolChoice::tool, SubmitWorkflowTitleTool::NAME)]
#[MaxSteps(1)]
class WorkflowBuilderTitleAgent implements Agent, HasTools
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'INSTRUCTIONS'
        Name a workflow automation from the user's description of it.

        Use 3 to 6 words in Title Case that say what the workflow does. Leave out filler words like
        "workflow", "automation" or "system". For example: "when a GitHub PR is merged, tell the team
        in Slack" becomes "GitHub PR Merge Alert"; "every morning email me yesterday's signups"
        becomes "Daily Signup Email".

        Submit the title by calling the submit_workflow_title tool.
        INSTRUCTIONS;
    }

    public function tools(): iterable
    {
        return [new SubmitWorkflowTitleTool];
    }
}
