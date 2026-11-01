<?php

namespace App\Services\Workflows;

use App\Enums\Workflows\FlowControlNodeType;
use App\Exceptions\WorkflowValidationException;
use App\Models\Workflows\Workflow;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Compiles Gumloop-style per-node "Loop Mode": a reserved `config._loop`
 * key any node may carry (see docs/gumloop/output/raw/core-concepts/agent_node.md's
 * "Loop Mode Support"): into a real `loop` node, at publish time only,
 * never on the live draft.
 *
 * Deliberately does not touch `Services\Workflows\Engine\LoopCoordinator` or
 * `WorkflowRunner`'s runtime execution path at all: a node with `_loop`
 * enabled is rewritten into `{type: 'loop', config: {items_path,
 * workflow_id, max_concurrent, on_item_error}}` pointed at a small, hidden,
 * single-node child `Workflow` (`is_internal = true`) containing just that
 * node's original `{type, config}` (minus `_loop`). Every existing
 * loop/child-workflow execution, retry, failure-routing, and Run Log code
 * path is reused unchanged: this class only ever edits the graph that gets
 * frozen into a `WorkflowVersion`.
 *
 * The child workflow is found (and its graph kept in sync) by a
 * deterministic slug: `"loop-{$workflow->id}-{$node['key']}"`, rather than
 * a stored id, since `Workflow::replaceGraph()` deletes and recreates every
 * `workflow_nodes` row on every draft save, which would otherwise need its
 * own carry-forward logic to remember the mapping.
 */
class LoopModeCompiler
{
    private const array VALID_ON_ITEM_ERROR = ['fail_fast', 'continue', 'collect_errors'];

    public function __construct(private readonly NodeRegistry $registry) {}

    /**
     * @param  array<int, array{key: string, type: string, config: array<string, mixed>}>  $nodes
     * @return array<int, array{key: string, type: string, config: array<string, mixed>}>
     *
     * @throws WorkflowValidationException
     */
    public function compile(Workflow $workflow, array $nodes): array
    {
        $errors = [];
        $compiled = [];

        foreach ($nodes as $node) {
            $loopConfig = $node['config']['_loop'] ?? null;

            if ($loopConfig === null) {
                $compiled[] = $node;

                continue;
            }

            if (! is_array($loopConfig)) {
                $errors[] = "Node '{$node['key']}': config._loop must be an object.";

                continue;
            }

            if (! $this->registry->has($node['type'])) {
                $errors[] = "Node '{$node['key']}': loop mode isn't supported on flow-control node types.";

                continue;
            }

            $itemsPath = $loopConfig['items_path'] ?? null;

            if (! is_string($itemsPath) || $itemsPath === '') {
                $errors[] = "Node '{$node['key']}': loop mode requires config._loop.items_path.";

                continue;
            }

            $onItemError = $loopConfig['on_item_error'] ?? 'fail_fast';

            if (! in_array($onItemError, self::VALID_ON_ITEM_ERROR, true)) {
                $errors[] = "Node '{$node['key']}': config._loop.on_item_error must be one of: ".implode(', ', self::VALID_ON_ITEM_ERROR).'.';

                continue;
            }

            $child = $this->syncChildWorkflow($workflow, $node);

            $compiled[] = [
                'key' => $node['key'],
                'type' => FlowControlNodeType::Loop->value,
                'config' => [
                    'items_path' => $itemsPath,
                    'workflow_id' => $child->id,
                    'max_concurrent' => (int) ($loopConfig['max_concurrent'] ?? 1),
                    'on_item_error' => $onItemError,
                ],
            ];
        }

        if ($errors !== []) {
            throw new WorkflowValidationException($errors);
        }

        return $compiled;
    }

    /**
     * @param  array{key: string, type: string, config: array<string, mixed>}  $node
     */
    private function syncChildWorkflow(Workflow $workflow, array $node): Workflow
    {
        $slug = Str::slug("loop-{$workflow->id}-{$node['key']}");

        $child = Workflow::firstOrCreate(
            ['workspace_id' => $workflow->workspace_id, 'slug' => $slug],
            [
                'name' => "[Loop] {$workflow->name} → {$node['key']}",
                'is_internal' => true,
                'created_by' => $workflow->created_by,
            ],
        );

        $child->replaceGraph([
            'nodes' => [[
                'key' => 'item',
                'type' => $node['type'],
                'config' => Arr::except($node['config'], ['_loop']),
            ]],
            'edges' => [],
        ]);
        $child->publishVersion();

        return $child;
    }
}
