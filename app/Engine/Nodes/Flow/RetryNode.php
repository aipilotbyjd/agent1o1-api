<?php

namespace App\Engine\Nodes\Flow;

use App\Contracts\NodeHandler;
use App\Engine\NodeInput;
use App\Engine\NodeResult;

class RetryNode implements NodeHandler
{
    public function handle(NodeInput $input): NodeResult
    {
        $maxAttempts = (int) ($input->config['max_attempts'] ?? 3);
        $currentAttempt = (int) ($input->config['_attempt'] ?? 1);
        $delayMs = (int) ($input->config['delay_ms'] ?? 1000);

        if ($currentAttempt > 1 && $delayMs > 0) {
            usleep($delayMs * 1000);
        }

        return NodeResult::completed([
            'attempt' => $currentAttempt,
            'max_attempts' => $maxAttempts,
            'will_retry' => $currentAttempt < $maxAttempts,
        ]);
    }
}
