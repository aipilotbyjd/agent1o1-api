<?php

namespace App\Engine\Nodes\Flow;

use App\Contracts\NodeHandler;
use App\Engine\NodeInput;
use App\Engine\NodeResult;

class LoopNode implements NodeHandler
{
    public function handle(NodeInput $input): NodeResult
    {
        $items = $input->config['items'] ?? [];
        $batchSize = (int) ($input->config['batch_size'] ?? 0);

        if (! is_array($items)) {
            $items = [$items];
        }

        if ($batchSize > 0) {
            $items = array_chunk($items, $batchSize);
        }

        return NodeResult::withLoopItems(
            loopItems: array_values($items),
        );
    }
}
