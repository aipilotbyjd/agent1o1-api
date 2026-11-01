<?php

namespace App\Events\Runs;

use App\Models\Runs\Run;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RunFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Run $run) {}
}
