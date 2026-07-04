<?php

namespace App\Engine\Nodes\Core;

use App\Contracts\NodeHandler;
use App\Engine\NodeInput;
use App\Engine\NodeResult;

class TriggerNode implements NodeHandler
{
    public function handle(NodeInput $input): NodeResult
    {
        // Trigger node is a pass-through — its output IS the trigger data
        $triggerData = $input->variables['trigger_data'] ?? [];

        return NodeResult::completed($triggerData);
    }
}
