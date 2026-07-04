<?php

namespace App\Services\WorkflowBuilder;

use App\Models\Node;
use App\Models\WorkflowBuilderSession;

class ValidationService
{
    /**
     * @return array<int, array{node_id: string|null, issue: string}>
     */
    public function validate(WorkflowBuilderSession $session): array
    {
        $nodes = $session->nodes_draft ?? [];
        $edges = $session->edges_draft ?? [];

        return array_merge(
            $this->checkTrigger($nodes),
            $this->detectCycles($nodes, $edges),
            $this->detectOrphans($nodes, $edges),
            $this->checkRequiredConfig($nodes),
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<int, array{node_id: null, issue: string}>
     */
    private function checkTrigger(array $nodes): array
    {
        $hasTrigger = collect($nodes)->contains(
            fn ($n) => ($n['type'] ?? '') === 'trigger' || str_ends_with((string) ($n['type'] ?? ''), '_trigger')
        );

        return $hasTrigger ? [] : [['node_id' => null, 'issue' => 'Workflow has no trigger node']];
    }

    /**
     * DFS cycle detection.
     *
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<int, array<string, mixed>>  $edges
     * @return array<int, array{node_id: string, issue: string}>
     */
    private function detectCycles(array $nodes, array $edges): array
    {
        $adjacency = [];
        foreach ($nodes as $n) {
            $adjacency[$n['id']] = [];
        }
        foreach ($edges as $e) {
            $adjacency[$e['source'] ?? ''][] = $e['target'] ?? '';
        }

        $visited = [];
        $path = [];
        $errors = [];

        foreach (array_keys($adjacency) as $nodeId) {
            if (! isset($visited[$nodeId])) {
                $this->dfs($nodeId, $adjacency, $visited, $path, $errors);
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, array<string>>  $adjacency
     * @param  array<string, bool>  $visited
     * @param  array<string, bool>  $path
     * @param  array<int, array{node_id: string, issue: string}>  $errors
     */
    private function dfs(string $node, array $adjacency, array &$visited, array &$path, array &$errors): void
    {
        $visited[$node] = true;
        $path[$node] = true;

        foreach ($adjacency[$node] ?? [] as $neighbour) {
            if (! isset($visited[$neighbour])) {
                $this->dfs($neighbour, $adjacency, $visited, $path, $errors);
            } elseif (isset($path[$neighbour])) {
                $cycle = implode(' → ', array_keys($path)).' → '.$neighbour;
                $errors[] = ['node_id' => $node, 'issue' => "Creates a cycle: {$cycle}"];
            }
        }

        unset($path[$node]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<int, array<string, mixed>>  $edges
     * @return array<int, array{node_id: string, issue: string}>
     */
    private function detectOrphans(array $nodes, array $edges): array
    {
        $triggers = collect($nodes)->filter(
            fn ($n) => ($n['type'] ?? '') === 'trigger' || str_ends_with((string) ($n['type'] ?? ''), '_trigger')
        )->pluck('id')->all();

        if (empty($triggers)) {
            return [];
        }

        $reachable = [];
        $queue = $triggers;

        $adjacency = [];
        foreach ($edges as $e) {
            $adjacency[$e['source'] ?? ''][] = $e['target'] ?? '';
        }

        while (! empty($queue)) {
            $current = array_shift($queue);
            if (isset($reachable[$current])) {
                continue;
            }
            $reachable[$current] = true;
            foreach ($adjacency[$current] ?? [] as $next) {
                $queue[] = $next;
            }
        }

        $errors = [];
        foreach ($nodes as $node) {
            $id = $node['id'] ?? '';
            if (! isset($reachable[$id])) {
                $errors[] = ['node_id' => $id, 'issue' => 'Unreachable — not connected to any trigger node'];
            }
        }

        return $errors;
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<int, array{node_id: string, issue: string}>
     */
    private function checkRequiredConfig(array $nodes): array
    {
        $errors = [];
        $nodeTypes = collect($nodes)->pluck('type')->unique()->all();
        $schemas = Node::query()->whereIn('type', $nodeTypes)->pluck('config_schema', 'type');

        foreach ($nodes as $node) {
            $id = $node['id'] ?? '';
            $type = $node['type'] ?? '';
            $schema = $schemas[$type] ?? null;

            if (! $schema) {
                continue;
            }

            $required = $schema['required'] ?? [];
            $config = (array) ($node['config'] ?? []);

            foreach ($required as $field) {
                if (! array_key_exists($field, $config) || $config[$field] === null || $config[$field] === '') {
                    $errors[] = ['node_id' => $id, 'issue' => "Missing required config field: {$field}"];
                }
            }
        }

        return $errors;
    }
}
