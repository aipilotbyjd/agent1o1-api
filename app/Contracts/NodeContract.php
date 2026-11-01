<?php

namespace App\Contracts;

use App\Models\Runs\Run;

/**
 * Implemented by every node type that actually does work when the engine
 * reaches it. Flow-control node types (Condition/Router excepted: it does
 * evaluate and implements this) such as Merge, Delay's parent Loop(foreach),
 * HumanApproval, SubWorkflow, and Wait are driven directly by the engine's
 * graph traversal instead: see docs/WORKFLOWS_PLAN.md's "Node contract &
 * registry" section.
 */
interface NodeContract
{
    /**
     * The stable identifier stored in `workflow_nodes.type` and resolved by
     * `NodeRegistry`.
     */
    public function type(): string;

    /**
     * Must match a `node_categories.slug` row.
     */
    public function category(): string;

    /**
     * Short, human-readable label: what `WorkflowBuilderAgent`'s
     * `list_available_nodes` tool shows the model instead of the raw
     * `type()` string.
     */
    public function name(): string;

    /**
     * One sentence on what this node does: shown alongside `name()`.
     */
    public function description(): string;

    /**
     * JSON-schema-shaped description of this node's `config`, used to
     * validate both draft saves and publishes (`ConfigSchemaValidator`).
     * Every implementation's `config` may also carry a reserved `_loop` key
     * ({items_path, max_concurrent?, on_item_error?}) enabling Gumloop-style
     * per-node "Loop Mode" - `Services\Workflows\LoopModeCompiler` compiles
     * it away at publish time, so it's never seen by `execute()` and never
     * needs to be declared here.
     *
     * @return array<string, mixed>
     */
    public function configSchema(): array;

    /**
     * Execute the node and return its output payload, written to the owning
     * `NodeRun.output`.
     *
     * @param  array<string, mixed>  $config  The node's configured params (workflow_nodes.config).
     * @param  array<string, mixed>  $context  Run-scoped execution context (upstream node outputs, run input).
     * @return array<string, mixed>
     */
    public function execute(Run $run, array $config, array $context): array;
}
