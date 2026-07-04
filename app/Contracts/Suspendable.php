<?php

namespace App\Contracts;

use App\Engine\ExecutionPause;
use App\Engine\NodeInput;

interface Suspendable
{
    public function pause(NodeInput $input): ExecutionPause;
}
