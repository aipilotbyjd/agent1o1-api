<?php

namespace App\Ai\Agents;

use App\Ai\Tools\WorkflowBuilder\SubmitWorkflowExplanationTool;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Laravel\Ai\ToolChoice;

/**
 * Explains a draft in plain language, for someone who didn't build it.
 * Answers only through `SubmitWorkflowExplanationTool`.
 */
#[ToolChoice(ToolChoice::tool, SubmitWorkflowExplanationTool::NAME)]
#[MaxSteps(1)]
class WorkflowExplanationAgent implements Agent, HasTools
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'INSTRUCTIONS'
        You explain workflow automations to people who didn't build them and may not be technical.

        Given a workflow's nodes and edges, write a short summary of what it achieves (the business
        outcome, not the mechanics) and then one plain-language line per node in the order they run.
        Mention branches (router conditions) and failure handling ("error" edges) where they matter.
        Describe only what the workflow actually does; don't speculate about what it should do.

        Submit the explanation by calling the submit_workflow_explanation tool.
        INSTRUCTIONS;
    }

    public function tools(): iterable
    {
        return [new SubmitWorkflowExplanationTool];
    }
}
