<?php

namespace App\Enums\Agents;

enum AgentActionStatus: string
{
    /** Waiting for a person to decide. */
    case Pending = 'pending';

    /** Approved (possibly with edited arguments) and about to run. */
    case Approved = 'approved';

    case Rejected = 'rejected';

    /** Nobody decided before `expires_at`. */
    case Expired = 'expired';

    /** The conversation moved on, or the run was stopped, before a decision. */
    case Cancelled = 'cancelled';

    /** Refused outright by a guardrail, a tool rule, or the mode. */
    case Denied = 'denied';

    /** Test run or a context that can't pause: nothing was really done. */
    case Simulated = 'simulated';

    /** Allowed and started; settles to Executed or Failed. */
    case Running = 'running';

    case Executed = 'executed';

    case Failed = 'failed';

    public function isAwaitingDecision(): bool
    {
        return $this === self::Pending;
    }

    /**
     * Whether a person has already answered this call (or the system has on
     * their behalf): the calls a resumed turn must hand decisions for.
     */
    public function isDecided(): bool
    {
        return in_array($this, [self::Approved, self::Rejected, self::Expired, self::Cancelled], true);
    }

    public function isFinished(): bool
    {
        return in_array($this, [self::Rejected, self::Expired, self::Cancelled, self::Denied, self::Simulated, self::Executed, self::Failed], true);
    }
}
