<?php

namespace App\Services\WorkflowBuilder;

use App\Agents\Internal\NodeConfigurationAgent;
use App\Agents\Internal\NodeSuggestionAgent;

class SuggestionService
{
    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<int, array<string, mixed>>  $edges
     * @return array<int, array{node_type: string, node_name: string, reason: string, category: string, complexity: string}>
     */
    public function suggestNodes(array $nodes, array $edges, ?string $goal = null): array
    {
        $definition = json_encode(['nodes' => $nodes, 'edges' => $edges], JSON_PRETTY_PRINT);
        $prompt = "Here is the current workflow:\n{$definition}";

        if ($goal) {
            $prompt .= "\n\nThe user's goal: {$goal}";
        }

        $response = (new NodeSuggestionAgent)->prompt($prompt);

        return $response['suggestions'] ?? [];
    }

    /**
     * @return array{config: array<string, mixed>, explanation: string, validation_notes: string}
     */
    public function configureNode(string $nodeType, string $intent): array
    {
        $prompt = "Node type: {$nodeType}\nWhat the user wants to do: {$intent}";

        $response = (new NodeConfigurationAgent)->prompt($prompt);

        return [
            'config' => $response['config'] ?? [],
            'explanation' => $response['explanation'] ?? '',
            'validation_notes' => $response['validation_notes'] ?? '',
        ];
    }
}
