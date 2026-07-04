<?php

namespace App\Agents\Tools\Draft;

use App\Models\WorkflowBuilderSession;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ReadDraftWorkflowTool implements Tool
{
    public function __construct(private readonly WorkflowBuilderSession $session) {}

    public function description(): Stringable|string
    {
        return 'Read the current state of the workflow draft. Returns all nodes and edges, plus a reachability summary. Call this before making multiple changes to understand the current state.';
    }

    public function handle(Request $request): Stringable|string
    {
        $this->session->refresh();

        $nodes = $this->session->nodes_draft ?? [];
        $edges = $this->session->edges_draft ?? [];

        $triggerNodes = array_filter($nodes, fn ($n) => ($n['type'] ?? '') === 'trigger' || str_ends_with((string) ($n['type'] ?? ''), '_trigger'));

        $summary = [
            'title' => $this->session->title,
            'node_count' => count($nodes),
            'edge_count' => count($edges),
            'has_trigger' => count($triggerNodes) > 0,
            'nodes' => array_map(fn ($n) => [
                'id' => $n['id'],
                'type' => $n['type'],
                'name' => $n['name'],
                'has_config' => ! empty($n['config']),
            ], $nodes),
            'edges' => array_map(fn ($e) => [
                'source' => $e['source'],
                'target' => $e['target'],
            ], $edges),
        ];

        return json_encode($summary, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
