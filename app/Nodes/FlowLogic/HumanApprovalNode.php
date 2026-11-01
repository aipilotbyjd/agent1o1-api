<?php

namespace App\Nodes\FlowLogic;

use App\Enums\NodeCategory;
use App\Enums\Workflows\FlowControlNodeType;

/**
 * Engine-driven: does NOT implement `NodeContract`, not registered in
 * `NodeRegistry`. When the run reaches a `workflow_nodes.type = 'human_approval'`
 * node, `WorkflowRunner` pauses it (`NodeRunStatus::AwaitingApproval`) and
 * creates a `WorkflowApproval` row instead of calling `execute()`. Resumed via
 * `WorkflowRunner::resolveApproval()`. This class exists purely so the node
 * catalog/picker has something to point at: see docs/NODES_CATALOG.md.
 */
final class HumanApprovalNode
{
    public const string TYPE = FlowControlNodeType::HumanApproval->value;

    public const string CATEGORY = NodeCategory::FlowLogic->value;
}
