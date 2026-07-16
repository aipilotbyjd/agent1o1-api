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

test('configFor reads editor field values saved under data.values', function () {
    $node = [
        'id' => 'code',
        'type' => 'util.code',
        'data' => [
            'defKey' => 'util.code',
            'label' => 'Code (JavaScript)',
            'values' => ['code' => 'return { n: 42 };'],
            'status' => 'idle',
        ],
    ];

    expect(WorkflowGraph::configFor($node))->toBe(['code' => 'return { n: 42 };']);
});

test('configFor prefers an explicit config key over data', function () {
    $node = [
        'id' => 'code',
        'type' => 'util.code',
        'config' => ['code' => 'explicit'],
        'data' => ['values' => ['code' => 'from values']],
    ];

    expect(WorkflowGraph::configFor($node))->toBe(['code' => 'explicit']);
});

test('configFor falls back to data when no values key is present', function () {
    $node = [
        'id' => 'code',
        'type' => 'util.code',
        'data' => ['code' => 'legacy shape'],
    ];

    expect(WorkflowGraph::configFor($node))->toBe(['code' => 'legacy shape']);
});

test('configFor returns an empty array for an unconfigured node', function () {
    expect(WorkflowGraph::configFor(['id' => 'a', 'type' => 'trigger.manual']))->toBe([]);
});
