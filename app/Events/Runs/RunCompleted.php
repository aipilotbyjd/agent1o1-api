<?php

namespace App\Events\Runs;

use App\Models\Runs\Run;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fires whether `Run.runnable_type` is a `Workflow` or (once
 * docs/AGENTS_PLAN.md lands) an `AgentSession`: decouples side effects
 * (credit deduction, notifications) from `GraphAdvancer`/`AgentRunner`
 * needing to know about every consumer. See docs/STRUCTURE.md's
 * "Public vs. Internal API" section.
 */
class RunCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Run $run) {}
}
