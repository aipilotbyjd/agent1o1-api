<?php

namespace App\Ai\Agents\Builder;

use App\Ai\Agents\Concerns\SubmitsStructuredOutput;
use App\Ai\Tools\WorkflowBuilder\SubmitWorkflowImprovementsTool;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;

/**
 * Reviews a draft and suggests concrete improvements. The draft, the
 * validator's findings, and the node catalog are in the prompt
 * (`WorkflowBuilderAssistant`); answers only through
 * `SubmitWorkflowImprovementsTool`.
 */
#[MaxSteps(1)]
class WorkflowImprovementAgent implements Agent, HasStructuredOutput, HasTools
{
    use Promptable, SubmitsStructuredOutput;

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

        Submit the suggestions as your structured result.
        INSTRUCTIONS;
    }

    protected function submissionTool(): Tool
    {
        return new SubmitWorkflowImprovementsTool;
    }
}
