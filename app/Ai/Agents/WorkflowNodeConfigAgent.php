<?php

namespace App\Ai\Agents;

use App\Ai\Tools\WorkflowBuilder\SubmitNodeConfigTool;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Laravel\Ai\ToolChoice;

/**
 * Proposes a config for one node from a plain-language request ("post the
 * summary to #alerts"). The node's config schema, the draft, and the known
 * outputs of the nodes before it are in the prompt
 * (`WorkflowBuilderAssistant`); answers only through `SubmitNodeConfigTool`.
 */
#[ToolChoice(ToolChoice::tool, SubmitNodeConfigTool::NAME)]
#[MaxSteps(1)]
class WorkflowNodeConfigAgent implements Agent, HasTools
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'INSTRUCTIONS'
        You configure one node of a workflow automation. Given the node's config schema, the rest of
        the workflow, and what the user wants the node to do, write the node's config.

        Set every required field and only the optional fields the request calls for. To use data
        from an earlier node, write a template like {{ nodes.<key>.<field> }}; for the run's input,
        {{ input.<field> }}. Prefer fields listed as known outputs. Never write a credential into the
        config: reference a stored secret as {{ secrets.<NAME> }} and list it under needs_from_user.
        List anything else only the user can provide (IDs, channel names, addresses) there too.

        Submit the config by calling the submit_node_config tool.
        INSTRUCTIONS;
    }

    public function tools(): iterable
    {
        return [new SubmitNodeConfigTool];
    }
}
