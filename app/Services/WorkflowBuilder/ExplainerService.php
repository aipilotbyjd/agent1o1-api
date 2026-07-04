<?php

namespace App\Services\WorkflowBuilder;

use App\Agents\Internal\WorkflowDescriptionAgent;

class ExplainerService
{
    public function explain(array $nodes, array $edges): string
    {
        $definition = json_encode(['nodes' => $nodes, 'edges' => $edges], JSON_PRETTY_PRINT);

        return (string) (new WorkflowDescriptionAgent)->prompt("Explain this workflow:\n{$definition}");
    }
}
