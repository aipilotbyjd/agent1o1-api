<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The workflow editor used to write node references as `{{ <key>.output }}`
 * and `{{ <key>.output.<path> }}`, which the engine never resolved: its
 * templating context is `{ input, nodes: { <key>: <output> } }`, so those
 * values always came out empty. This rewrites them to the form the engine
 * reads, `{{nodes.<key>}}` / `{{nodes.<key>.<path>}}`, in every stored graph.
 *
 * Only references whose `<key>` is a node in the same graph are touched, so
 * `{{ input.output }}` or a run-input field that happens to be called
 * `output` is left alone.
 */
return new class extends Migration
{
    private const string PATTERN = '/\{\{\s*([A-Za-z0-9_]+)\.output((?:\.[A-Za-z0-9_]+|\[\d+\])*)\s*\}\}/';

    public function up(): void
    {
        DB::table('workflow_nodes')
            ->select('workflow_id')
            ->distinct()
            ->orderBy('workflow_id')
            ->pluck('workflow_id')
            ->each(function (string $workflowId): void {
                $nodes = DB::table('workflow_nodes')->where('workflow_id', $workflowId)->get(['id', 'key', 'config']);
                $keys = $nodes->pluck('key')->all();

                foreach ($nodes as $node) {
                    $this->rewriteColumn('workflow_nodes', $node->id, 'config', $node->config, fn (mixed $config) => $this->rewrite($config, $keys));
                }
            });

        $this->rewriteGraphs('workflow_versions', 'graph');
        $this->rewriteGraphs('workflow_builder_sessions', 'draft_graph');
        $this->rewriteGraphs('workflow_builder_draft_versions', 'graph_snapshot');
        $this->rewriteGraphs('workflow_templates', 'graph');
    }

    public function down(): void
    {
        // The old form never resolved, so there is nothing worth restoring.
    }

    private function rewriteGraphs(string $table, string $column): void
    {
        DB::table($table)->whereNotNull($column)->orderBy('id')->chunkById(200, function ($rows) use ($table, $column): void {
            foreach ($rows as $row) {
                $this->rewriteColumn($table, $row->id, $column, $row->{$column}, function (mixed $graph): mixed {
                    if (! is_array($graph) || ! is_array($graph['nodes'] ?? null)) {
                        return $graph;
                    }

                    $keys = array_values(array_filter(array_map(fn ($node) => $node['key'] ?? null, $graph['nodes']), 'is_string'));

                    foreach ($graph['nodes'] as $index => $node) {
                        if (is_array($node) && array_key_exists('config', $node)) {
                            $graph['nodes'][$index]['config'] = $this->rewrite($node['config'], $keys);
                        }
                    }

                    return $graph;
                });
            }
        });
    }

    private function rewriteColumn(string $table, mixed $id, string $column, ?string $json, Closure $rewrite): void
    {
        if ($json === null || ! str_contains($json, '.output')) {
            return;
        }

        $decoded = json_decode($json, true);
        $rewritten = $rewrite($decoded);

        if ($rewritten !== $decoded) {
            DB::table($table)->where('id', $id)->update([$column => json_encode($rewritten)]);
        }
    }

    /**
     * @param  array<int, string>  $keys
     */
    private function rewrite(mixed $value, array $keys): mixed
    {
        if (is_array($value)) {
            return array_map(fn (mixed $item) => $this->rewrite($item, $keys), $value);
        }

        if (! is_string($value) || ! str_contains($value, '{{')) {
            return $value;
        }

        return preg_replace_callback(
            self::PATTERN,
            fn (array $match): string => in_array($match[1], $keys, true)
                ? '{{nodes.'.$match[1].$match[2].'}}'
                : $match[0],
            $value,
        );
    }
};
