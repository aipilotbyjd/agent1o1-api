<?php

namespace App\Contracts;

use App\Engine\NodeInput;
use App\Engine\NodeResult;

interface NodeHandler
{
    public function handle(NodeInput $input): NodeResult;
}
