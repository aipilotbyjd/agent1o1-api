<?php

namespace App\Enums\Workflows;

/**
 * Node types the engine drives directly (graph traversal / pause-resume),
 * rather than resolving through `NodeRegistry`/`NodeContract::execute()`:
 * see docs/WORKFLOWS_PLAN.md's "Node contract & registry" section.
 * `WorkflowRunner::executeStep()` branches on this before falling through to
 * the normal `NodeContract` path.
 */
enum FlowControlNodeType: string
{
    case HumanApproval = 'human_approval';
    case Wait = 'wait';
    case SubWorkflow = 'subflow';
    case Loop = 'loop';
    case JoinPaths = 'join_paths';

    /**
     * How the engine actually drives this node, phrased as wiring
     * instructions for the builder assistant (`InspectNodeSchemaTool`):
     * these types have no `NodeContract` class whose description could say
     * it. Kept beside the cases so a change to the engine's behaviour has
     * one obvious place to be re-described.
     */
    public function builderGuide(): string
    {
        return match ($this) {
            self::HumanApproval => 'Pauses the run until a workspace member approves or rejects it. Approved: the run continues along this node\'s normal edges, with output {"approved": true}. Rejected: the node fails, so connect an edge with the condition "error" for the rejected path, without one, a rejection fails the whole run.',
            self::Wait => 'Pauses the run until an external system calls the run\'s callback URL; the JSON body of that callback becomes this node\'s output. With timeout_seconds set, a timeout fails the node (connect an "error" edge to handle it) unless continue_on_timeout is true, in which case the run continues with output {"timed_out": true}.',
            self::SubWorkflow => 'Runs another published workflow from this workspace as a child run and waits for it; this node\'s output is the child run\'s output (its output values by name, when the child has workflow_output nodes). Get workflow_id from list_workflows; "input" becomes the child run\'s input, keyed by the child\'s input names. A failed child fails this node (connect an "error" edge to handle it).',
            self::Loop => 'Runs a published child workflow (workflow_id, from list_workflows) once per item of the list at items_path: a plain dot path such as "nodes.fetch.body.items", not a {{ }} template. Each child run gets the input {"item": ..., "index": ...}. Output: {"results": [...], "errors": [...]}. To repeat a single node per item, prefer that node\'s Loop Mode instead: add "_loop": {"items_path": "..."} to its config and reference the item as {{ input.item }}.',
            self::JoinPaths => 'Waits until every branch leading into it has finished (or been skipped) before continuing: place it where parallel paths or router branches meet again. Outputs nothing of its own; reference the branches\' nodes directly.',
        };
    }

    /**
     * Stand-in output for a dry run, where the engine's output shape is
     * fixed: null where it depends on something outside the graph (a
     * callback payload, a child workflow's result).
     *
     * @return array<string, mixed>|null
     */
    public function sampleOutput(): ?array
    {
        return match ($this) {
            self::HumanApproval => ['approved' => true],
            self::Loop => ['results' => [], 'errors' => []],
            self::JoinPaths => [],
            self::Wait, self::SubWorkflow => null,
        };
    }

    /**
     * Types whose `workflow_id` config points at another workflow.
     */
    public function runsChildWorkflow(): bool
    {
        return $this === self::SubWorkflow || $this === self::Loop;
    }
}
