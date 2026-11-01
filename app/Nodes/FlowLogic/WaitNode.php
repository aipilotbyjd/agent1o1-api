<?php

namespace App\Nodes\FlowLogic;

use App\Enums\NodeCategory;
use App\Enums\Workflows\FlowControlNodeType;

/**
 * Engine-driven: see `HumanApprovalNode`'s docblock for the general pattern.
 * `WorkflowRunner` hands the node a one-time `callback_token` +
 * `callback_expires_at` and pauses it (`NodeRunStatus::AwaitingCallback`).
 * Resumed via `WorkflowRunner::resolveCallback()` (token-authenticated,
 * `POST /hooks/wait/{token}`: see `routes/webhooks.php`) or
 * `WorkflowRunner::expireWait()` once `callback_expires_at` passes
 * (`ExpireStaleWaitsJob`).
 *
 * Config: `timeout_seconds` (nullable: omit for no expiry),
 * `continue_on_timeout` (bool, default false).
 */
final class WaitNode
{
    public const string TYPE = FlowControlNodeType::Wait->value;

    public const string CATEGORY = NodeCategory::FlowLogic->value;
}
