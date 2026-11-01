<?php

namespace App\Nodes\FlowLogic;

use App\Enums\NodeCategory;
use App\Enums\Workflows\FlowControlNodeType;

/**
 * Engine-driven: the old project's `MergeNode`. Doesn't pause a `NodeRun`
 * the way Approval/Wait/SubWorkflow/Loop do; instead `GraphAdvancer` simply
 * declines to create-and-dispatch this node until every one of its incoming
 * (non-error) edges has a terminal source `NodeRun`: see
 * `GraphAdvancer::isJoinReady()`. Waits, doesn't block: every branch but the
 * last one to arrive is a no-op.
 */
final class JoinPathsNode
{
    public const string TYPE = FlowControlNodeType::JoinPaths->value;

    public const string CATEGORY = NodeCategory::FlowLogic->value;
}
