<?php

use App\Engine\WorkflowGraph;

test('compiles a linear graph with correct start nodes and successors', function () {
    $graph = WorkflowGraph::compile(
        [
            ['id' => 'a', 'type' => 'trigger', 'name' => 'Start'],
            ['id' => 'b', 'type' => 'transform', 'name' => 'Step'],
            ['id' => 'c', 'type' => 'transform', 'name' => 'End'],
        ],
        [
            ['source' => 'a', 'target' => 'b'],
            ['source' => 'b', 'target' => 'c'],
        ],
    );

    expect($graph->startNodes)->toBe(['a'])
        ->and($graph->getSuccessors('a'))->toBe(['b'])
        ->and($graph->getSuccessors('b'))->toBe(['c'])
        ->and($graph->getPredecessors('c'))->toBe(['b'])
        ->and($graph->nodeCount())->toBe(3);
});

test('detects cycles and refuses to compile', function () {
    WorkflowGraph::compile(
        [
            ['id' => 'a', 'type' => 'transform'],
            ['id' => 'b', 'type' => 'transform'],
        ],
        [
            ['source' => 'a', 'target' => 'b'],
            ['source' => 'b', 'target' => 'a'],
        ],
    );
})->throws(RuntimeException::class, 'cycle');

test('parallel branches both appear as successors', function () {
    $graph = WorkflowGraph::compile(
        [
            ['id' => 'start', 'type' => 'trigger'],
            ['id' => 'left', 'type' => 'transform'],
            ['id' => 'right', 'type' => 'transform'],
        ],
        [
            ['source' => 'start', 'target' => 'left'],
            ['source' => 'start', 'target' => 'right'],
        ],
    );

    expect($graph->getSuccessors('start'))->toContain('left')->toContain('right');
});

test('edges with handles are filterable', function () {
    $graph = WorkflowGraph::compile(
        [
            ['id' => 'cond', 'type' => 'condition'],
            ['id' => 'yes', 'type' => 'transform'],
            ['id' => 'no', 'type' => 'transform'],
        ],
        [
            ['source' => 'cond', 'target' => 'yes', 'sourceHandle' => 'true'],
            ['source' => 'cond', 'target' => 'no', 'sourceHandle' => 'false'],
        ],
    );

    $trueEdges = $graph->getEdgesFrom('cond', 'true');

    expect($trueEdges)->toHaveCount(1)
        ->and($trueEdges[0]['target'])->toBe('yes');
});

test('edges referencing unknown nodes are ignored', function () {
    $graph = WorkflowGraph::compile(
        [['id' => 'a', 'type' => 'trigger']],
        [['source' => 'a', 'target' => 'ghost']],
    );

    expect($graph->getSuccessors('a'))->toBe([]);
});
