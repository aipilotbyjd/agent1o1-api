<?php

namespace App\Services\Workflows;

/**
 * What changed between two versions of a draft graph, as a list of actions a
 * chat UI can show as chips under the assistant's reply ("Added send_email",
 * "Connected fetch → send_email"). Nodes are matched by `key` and edges by
 * `(from, to, condition)`: the same identities the builder edits by.
 */
final class DraftDiff
{
    /**
     * @param  array{nodes?: array<int, array<string, mixed>>, edges?: array<int, array<string, mixed>>}  $before
     * @param  array{nodes?: array<int, array<string, mixed>>, edges?: array<int, array<string, mixed>>}  $after
     * @return array<int, array{type: string, key?: string, node_type?: string, from?: string, to?: string, condition?: string|null}>
     */
    public static function between(array $before, array $after): array
    {
        $beforeNodes = collect($before['nodes'] ?? [])->keyBy('key');
        $afterNodes = collect($after['nodes'] ?? [])->keyBy('key');

        $actions = [];

        foreach ($afterNodes as $key => $node) {
            $previous = $beforeNodes->get($key);

            if ($previous === null) {
                $actions[] = ['type' => 'node_added', 'key' => (string) $key, 'node_type' => $node['type']];
            } elseif ($previous['type'] !== $node['type'] || ($previous['config'] ?? []) != ($node['config'] ?? [])) {
                $actions[] = ['type' => 'node_updated', 'key' => (string) $key, 'node_type' => $node['type']];
            }
        }

        foreach ($beforeNodes as $key => $node) {
            if (! $afterNodes->has($key)) {
                $actions[] = ['type' => 'node_removed', 'key' => (string) $key, 'node_type' => $node['type']];
            }
        }

        $beforeEdges = self::edgesByIdentity($before['edges'] ?? []);
        $afterEdges = self::edgesByIdentity($after['edges'] ?? []);

        foreach (array_diff_key($afterEdges, $beforeEdges) as $edge) {
            $actions[] = ['type' => 'edge_added', ...$edge];
        }

        foreach (array_diff_key($beforeEdges, $afterEdges) as $edge) {
            $actions[] = ['type' => 'edge_removed', ...$edge];
        }

        return $actions;
    }

    /**
     * @param  array<int, array<string, mixed>>  $edges
     * @return array<string, array{from: string, to: string, condition: string|null}>
     */
    private static function edgesByIdentity(array $edges): array
    {
        $byIdentity = [];

        foreach ($edges as $edge) {
            $normalized = ['from' => $edge['from'], 'to' => $edge['to'], 'condition' => $edge['condition'] ?? null];
            $byIdentity[json_encode($normalized)] = $normalized;
        }

        return $byIdentity;
    }
}
