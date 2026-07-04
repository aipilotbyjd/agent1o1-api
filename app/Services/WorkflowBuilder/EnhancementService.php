<?php

namespace App\Services\WorkflowBuilder;

use App\Agents\Internal\WorkflowEnhancementAgent;

class EnhancementService
{
    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<int, array<string, mixed>>  $edges
     * @return array<int, array{title: string, description: string, impact: string, priority: string, effort: string}>
     */
    public function suggestEnhancements(array $nodes, array $edges): array
    {
        $definition = json_encode(['nodes' => $nodes, 'edges' => $edges], JSON_PRETTY_PRINT);

        $response = (new WorkflowEnhancementAgent)->prompt(
            "Analyze this workflow and suggest improvements:\n{$definition}"
        );

        return array_values(
            array_filter(
                $response['suggestions'] ?? [],
                fn ($s) => is_array($s) && ! empty($s['title'])
            )
        );
    }
}
