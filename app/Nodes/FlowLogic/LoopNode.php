<?php

namespace App\Nodes\FlowLogic;

use App\Enums\NodeCategory;
use App\Enums\Workflows\FlowControlNodeType;

/**
 * Engine-driven (`foreach` mode only: a `map` mode implementing
 * `NodeContract` is deferred, see docs/WORKFLOWS_PLAN.md). Loop Mode is real
 * child `runs` rows, not an in-handler batch:
 * `Services\Workflows\Engine\LoopCoordinator` starts one child run per item
 * (up to `max_concurrent`, releasing more as earlier ones settle) against
 * `config.workflow_id`, and resumes this node via
 * `WorkflowRunner::resolveSubWorkflow()` once every item has settled.
 *
 * Config: `items_path` (dot path into the run's templating context,
 * required), `workflow_id` (required), `max_concurrent` (default 1),
 * `on_item_error` (`fail_fast`|`continue`|`collect_errors`, default
 * `fail_fast`).
 */
final class LoopNode
{
    public const string TYPE = FlowControlNodeType::Loop->value;

    public const string CATEGORY = NodeCategory::FlowLogic->value;
}
