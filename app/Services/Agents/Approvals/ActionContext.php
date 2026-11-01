<?php

namespace App\Services\Agents\Approvals;

use App\Enums\Agents\AutonomyMode;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentSession;
use App\Models\Agents\WorkspaceAgentPolicy;
use App\Models\Runs\Run;

/**
 * Where a tool call is being made from, as far as approvals care: which
 * agent, in which conversation and run, under which mode, and whether this
 * context can wait for a person at all.
 *
 * `$canPause` is false for a stateless call (`AgentRunner::ask()`, used by
 * evals): there is no conversation to resume, so a call that would ask is
 * simulated instead of left hanging.
 */
final readonly class ActionContext
{
    public function __construct(
        public Agent $agent,
        public Run $run,
        public ?AgentSession $session,
        public AutonomyMode $mode,
        public bool $testMode,
        public bool $canPause,
        public WorkspaceAgentPolicy $policy,
    ) {}
}
