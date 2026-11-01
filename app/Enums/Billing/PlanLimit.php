<?php

namespace App\Enums\Billing;

/**
 * Keys looked up against `Plan.limits` (a plain `[key => int]` JSON map) via
 * `Plan::limit()`, and enforced by `Services\Billing\PlanLimitGate`.
 *
 * The cases are the contract for what a plan can actually cap: `PlanLimitGate`
 * resolves usage through an exhaustive `match` over this enum, so a key added
 * to the JSON without a case here can't silently go unenforced. The numbers
 * themselves stay data: editable per plan row without a deploy.
 */
enum PlanLimit: string
{
    case Workflows = 'workflows';
    case Agents = 'agents';
    case Members = 'members';
    case Pages = 'pages';
    case ActiveTriggers = 'active_triggers';

    public function label(): string
    {
        return match ($this) {
            self::Workflows => 'workflows',
            self::Agents => 'agents',
            self::Members => 'members',
            self::Pages => 'pages',
            self::ActiveTriggers => 'active triggers',
        };
    }
}
