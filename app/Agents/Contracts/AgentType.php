<?php

namespace App\Agents\Contracts;

/**
 * The two first-class agent kinds the platform runs.
 *
 *  - User: customer-created agents stored in the `agents` table, workspace
 *    scoped, billed against workspace budgets.
 *  - Internal: platform-owned agents defined in code (planner, moderation,
 *    eval judge, ...) and registered in App\Agents\Internal\Registry.
 */
enum AgentType: string
{
    case User = 'user';
    case Internal = 'internal';
}
