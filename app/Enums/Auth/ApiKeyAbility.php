<?php

namespace App\Enums\Auth;

/**
 * Scopes an API key can carry, checked by `EnsureApiKeyIsValid` on the
 * Public API routes.
 *
 * Note that `WorkflowsWrite` covers both editing workflows *and* causing them
 * to execute (start, cancel, retry a run). That has been its meaning since
 * the start-run endpoint shipped, and splitting execution into a scope of its
 * own now would silently downgrade every key already issued.
 */
enum ApiKeyAbility: string
{
    case All = '*';

    /** Read workflows, their graphs, and their input interfaces. */
    case WorkflowsRead = 'workflows:read';

    /** Author workflows (create, edit, publish) and run them (start, cancel, retry). */
    case WorkflowsWrite = 'workflows:write';

    /** Start agent sessions, send messages, and list the agents available to do so with. */
    case AgentsInvoke = 'agents:invoke';

    /** Read run history, including per-node results. */
    case RunsRead = 'runs:read';

    /** Read the connector catalog and provision the workspace's credentials. */
    case ConnectorsManage = 'connectors:manage';
}
