<?php

namespace App\Nodes\FlowLogic;

use App\Enums\NodeCategory;
use App\Enums\Workflows\FlowControlNodeType;

/**
 * Engine-driven: see `HumanApprovalNode`'s docblock for the general pattern.
 * `Services\Workflows\Engine\SubWorkflowCoordinator` starts a child `runs`
 * row (`workflow_id` from `config.workflow_id`) and pauses this node until
 * the child settles, then resumes (or fails) it via
 * `WorkflowRunner::resolveSubWorkflow()`.
 *
 * Config: `workflow_id` (required), `input` (nullable; literal object passed
 * as the child run's input; `{{ }}` templating lands in Stage 5).
 */
final class SubWorkflowNode
{
    public const string TYPE = FlowControlNodeType::SubWorkflow->value;

    public const string CATEGORY = NodeCategory::FlowLogic->value;
}
