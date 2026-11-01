<?php

namespace App\Ai\Agents;

use App\Ai\Tools\WorkflowBuilder\SubmitWorkflowImprovementsTool;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Laravel\Ai\ToolChoice;

/**
 * Reviews a draft and suggests concrete improvements. The draft, the
 * validator's findings, and the node catalog are in the prompt
 * (`WorkflowBuilderAssistant`); answers only through
 * `SubmitWorkflowImprovementsTool`.
 */
#[ToolChoice(ToolChoice::tool, SubmitWorkflowImprovementsTool::NAME)]
#[MaxSteps(1)]
class WorkflowImprovementAgent implements Agent, HasTools
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'INSTRUCTIONS'
        You review workflow automations and suggest concrete improvements, most impactful first.

        Look for: problems the validator reported; steps that call an external service with no
        "error" edge to handle a failure; data used before it is checked; results nobody is told
        about; independent steps that run one after another but could run in parallel; and
        credentials written into configs instead of {{ secrets.<NAME> }} references.

        Give 3 to 7 suggestions, each specific to this workflow: name the nodes involved. If a fix
        needs a new node, pick its type from the catalog. Don't pad the list with generic advice.

        Submit the suggestions by calling the submit_workflow_improvements tool.
        INSTRUCTIONS;
    }

    public function tools(): iterable
    {
        return [new SubmitWorkflowImprovementsTool];
    }
}
