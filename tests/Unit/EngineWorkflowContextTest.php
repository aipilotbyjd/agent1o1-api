<?php

use App\Engine\Execution\OutputBuffer;
use App\Engine\NodeResult;
use App\Engine\WorkflowContext;
use App\Engine\WorkflowGraph;

// ── Helpers ───────────────────────────────────────────────────────────────────

function engineMakeGraph(array $nodes, array $edges): WorkflowGraph
{
    return WorkflowGraph::compile($nodes, $edges);
}

function engineMakeContext(WorkflowGraph $graph): WorkflowContext
{
    return new WorkflowContext(
        graph: $graph,
        outputs: new OutputBuffer('test-exec', $graph->downstreamConsumers),
        executionId: 'test-exec',
        workspaceId: 'ws-1',
    );
}

// ── Failed-node branch termination ───────────────────────────────────────────

test('failed node does not enqueue its normal successors', function () {
    $graph = engineMakeGraph(
        [
            ['id' => 'a', 'type' => 'transform', 'name' => 'A'],
            ['id' => 'b', 'type' => 'transform', 'name' => 'B'],
        ],
        [['source' => 'a', 'target' => 'b']],
    );

    $ctx = engineMakeContext($graph);
    $ctx->popReadyNodes(); // drain start nodes

    $ctx->markCompleted('a', NodeResult::failed('boom'));

    expect($ctx->popReadyNodes())->toBe([]);
});

test('failed node enqueues a TryCatch successor', function () {
    $graph = engineMakeGraph(
        [
            ['id' => 'a', 'type' => 'transform', 'name' => 'A'],
            ['id' => 'tc', 'type' => 'try_catch', 'name' => 'TC'],
        ],
        [['source' => 'a', 'target' => 'tc']],
    );

    $ctx = engineMakeContext($graph);
    $ctx->popReadyNodes();

    $ctx->markCompleted('a', NodeResult::failed('oops'));

    expect($ctx->popReadyNodes())->toBe(['tc']);
});

test('failed node error payload is accessible via gatherInputData for a TryCatch successor', function () {
    $graph = engineMakeGraph(
        [
            ['id' => 'a', 'type' => 'transform', 'name' => 'A'],
            ['id' => 'tc', 'type' => 'try_catch', 'name' => 'TC'],
        ],
        [['source' => 'a', 'target' => 'tc']],
    );

    $ctx = engineMakeContext($graph);
    $ctx->popReadyNodes();

    $ctx->markCompleted('a', NodeResult::failed('bad thing', 'ERR_001'));

    $inputData = $ctx->gatherInputData('tc');
    expect($inputData)->toHaveKey('a')
        ->and($inputData['a'])->toMatchArray([
            '__failed' => true,
            'error' => ['message' => 'bad thing', 'code' => 'ERR_001'],
        ]);
});

test('successful node enqueues all active-branch successors normally', function () {
    $graph = engineMakeGraph(
        [
            ['id' => 'a', 'type' => 'transform', 'name' => 'A'],
            ['id' => 'b', 'type' => 'transform', 'name' => 'B'],
            ['id' => 'c', 'type' => 'transform', 'name' => 'C'],
        ],
        [
            ['source' => 'a', 'target' => 'b'],
            ['source' => 'a', 'target' => 'c'],
        ],
    );

    $ctx = engineMakeContext($graph);
    $ctx->popReadyNodes();

    $ctx->markCompleted('a', NodeResult::completed(['ok' => true]));

    $ready = $ctx->popReadyNodes();
    expect($ready)->toContain('b')->toContain('c');
});

// ── restoreState ─────────────────────────────────────────────────────────────

test('restoreState clears start-node seeds and applies saved in-degrees', function () {
    $graph = engineMakeGraph(
        [
            ['id' => 'trigger', 'type' => 'trigger', 'name' => 'T'],
            ['id' => 'step', 'type' => 'transform', 'name' => 'S'],
        ],
        [['source' => 'trigger', 'target' => 'step']],
    );

    $ctx = engineMakeContext($graph);

    // Restore mid-execution: trigger already done, step still waiting for in-degree
    $ctx->restoreState(
        remainingInDegree: ['trigger' => 0, 'step' => 1],
        nextSequence: 5,
    );

    expect($ctx->popReadyNodes())->toBe([]);
    expect($ctx->nextSequence())->toBe(5);
});

test('requeueReadyNode adds a node to the ready queue', function () {
    $graph = engineMakeGraph(
        [['id' => 'n', 'type' => 'transform', 'name' => 'N']],
        [],
    );

    $ctx = engineMakeContext($graph);
    $ctx->popReadyNodes();

    $ctx->requeueReadyNode('n');

    expect($ctx->popReadyNodes())->toBe(['n']);
});

// ── forLoopIteration ─────────────────────────────────────────────────────────

test('forLoopIteration starts with an empty ready queue', function () {
    $graph = engineMakeGraph(
        [
            ['id' => 'loop', 'type' => 'loop', 'name' => 'Loop'],
            ['id' => 'body', 'type' => 'transform', 'name' => 'Body'],
        ],
        [['source' => 'loop', 'target' => 'body']],
    );

    $buffer = new OutputBuffer('iter-0', []);
    $ctx = WorkflowContext::forLoopIteration(
        graph: $graph,
        outputs: $buffer,
        executionId: 'test',
        workspaceId: 'ws',
        variables: ['loop_current_item' => 'foo'],
    );

    expect($ctx->popReadyNodes())->toBe([]);
});

test('markBodyNodeCompleted stores output without queueing successors', function () {
    $graph = engineMakeGraph(
        [
            ['id' => 'loop', 'type' => 'loop', 'name' => 'Loop'],
            ['id' => 'body', 'type' => 'transform', 'name' => 'Body'],
            ['id' => 'next', 'type' => 'transform', 'name' => 'Next'],
        ],
        [
            ['source' => 'loop', 'target' => 'body'],
            ['source' => 'body', 'target' => 'next'],
        ],
    );

    $buffer = new OutputBuffer('iter-0', []);
    $ctx = WorkflowContext::forLoopIteration(
        graph: $graph,
        outputs: $buffer,
        executionId: 'test',
        workspaceId: 'ws',
        variables: [],
    );

    $ctx->markBodyNodeCompleted('body', NodeResult::completed(['result' => 42]));

    expect($buffer->get('body'))->toBe(['result' => 42]);
    // markBodyNodeCompleted does NOT manipulate the ready queue
    expect($ctx->popReadyNodes())->toBe([]);
});
