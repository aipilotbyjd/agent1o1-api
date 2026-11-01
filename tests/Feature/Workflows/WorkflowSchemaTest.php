<?php

use App\Enums\NodeRunStatus;
use App\Enums\RunStatus;
use App\Models\Nodes\NodeCategory;
use App\Models\Runs\NodeRun;
use App\Models\Runs\Run;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowEdge;
use App\Models\Workflows\WorkflowNode;
use Illuminate\Database\UniqueConstraintViolationException;

it('seeds the core node categories', function () {
    $this->syncSetup('node-categories');

    expect(NodeCategory::query()->pluck('slug')->sort()->values()->all())->toBe([
        'ai-automation', 'custom', 'data-transform', 'flow-logic', 'inputs-outputs', 'triggers-events',
    ]);
});

it('publishes a workflow version and points current_version_id at it', function () {
    $workflow = Workflow::factory()->published()->create();

    expect($workflow->fresh()->isPublished())->toBeTrue();
    expect($workflow->currentVersion->version)->toBe(1);
});

it('builds a linear graph of nodes and edges scoped to a workflow', function () {
    $workflow = Workflow::factory()->create();

    $input = WorkflowNode::create(['workflow_id' => $workflow->id, 'key' => 'input', 'type' => 'transform', 'config' => []]);
    $output = WorkflowNode::create(['workflow_id' => $workflow->id, 'key' => 'output', 'type' => 'transform', 'config' => []]);

    $edge = WorkflowEdge::create([
        'workflow_id' => $workflow->id,
        'from_node_id' => $input->id,
        'to_node_id' => $output->id,
    ]);

    expect($workflow->nodes)->toHaveCount(2);
    expect($input->outgoingEdges->first()->is($edge))->toBeTrue();
    expect($output->incomingEdges->first()->is($edge))->toBeTrue();
});

it('rejects a duplicate node key within the same workflow', function () {
    $workflow = Workflow::factory()->create();
    WorkflowNode::create(['workflow_id' => $workflow->id, 'key' => 'dup', 'type' => 'transform', 'config' => []]);

    WorkflowNode::create(['workflow_id' => $workflow->id, 'key' => 'dup', 'type' => 'transform', 'config' => []]);
})->throws(UniqueConstraintViolationException::class);

it('creates a run pointed at a workflow and node runs pointed at the run', function () {
    $workflow = Workflow::factory()->published()->create();

    $run = Run::factory()->forWorkflow($workflow)->create();

    expect($run->runnable_type)->toBe('workflow');
    expect($run->runnable_id)->toBe($workflow->id);
    expect($run->status)->toBe(RunStatus::Pending);

    $nodeRun = NodeRun::factory()->forRun($run)->create(['key' => 'input', 'type' => 'transform']);

    expect($run->nodeRuns)->toHaveCount(1);
    expect($nodeRun->status)->toBe(NodeRunStatus::Pending);
});
