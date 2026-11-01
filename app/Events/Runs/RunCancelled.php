<?php

namespace App\Events\Runs;

use App\Models\Runs\Run;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A `Run` stopped on request rather than by reaching a terminal state of its
 * own: see `WorkflowRunner::cancel()`. Sibling of `RunCompleted`/`RunFailed`
 * so consumers can treat "the run is over" uniformly.
 */
class RunCancelled
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Run $run,
        public readonly ?User $cancelledBy = null,
    ) {}
}
