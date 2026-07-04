<?php

namespace App\Engine\Nodes\Flow;

use App\Contracts\NodeHandler;
use App\Engine\NodeInput;
use App\Engine\NodeResult;

class TryCatchNode implements NodeHandler
{
    public function handle(NodeInput $input): NodeResult
    {
        // A failed predecessor stores ['__failed' => true, 'error' => [...]] in the
        // output buffer (see WorkflowContext::markCompleted). Scan all inputs for it.
        $hasError = false;
        $errorData = null;

        foreach ($input->inputData as $predOutput) {
            if (is_array($predOutput) && ($predOutput['__failed'] ?? false) === true) {
                $hasError = true;
                $errorData = $predOutput['error'] ?? null;
                break;
            }
        }

        return NodeResult::withBranches(
            output: [
                'has_error' => $hasError,
                'error' => $errorData,
            ],
            activeBranches: $hasError ? ['catch'] : ['try'],
        );
    }
}
