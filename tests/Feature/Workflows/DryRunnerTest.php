<?php

use App\Services\Workflows\DryRunner;

it('simulates a valid graph and reports references into an unknown output shape as unverified', function () {
    $graph = [
        'nodes' => [
            ['key' => 'a', 'type' => 'run_code', 'config' => [
                'operations' => [['op' => 'set', 'output' => 'result', 'value' => '1']],
            ]],
            ['key' => 'b', 'type' => 'run_code', 'config' => [
                'operations' => [
                    ['op' => 'set', 'output' => 'ok', 'value' => '{{ nodes.a.result }}'],
                    ['op' => 'set', 'output' => 'missing', 'value' => '{{ nodes.a.does_not_exist }}'],
                ],
            ]],
        ],
        'edges' => [
            ['from' => 'a', 'to' => 'b', 'condition' => null],
        ],
    ];

    $result = app(DryRunner::class)->run($graph);

    expect($result['issues'])->toBe([]);
    expect($result['steps'])->toHaveCount(2);
    expect($result['steps'][0]['key'])->toBe('a');
    expect($result['steps'][1]['key'])->toBe('b');
    // Node `a` has never run or been pinned, so its output shape is unknown:
    // neither reference can be judged, and neither is a false alarm.
    expect($result['warnings'])->toBe([]);
    expect($result['unverified'])->toHaveCount(2);
    expect($result['steps'][0]['sample_output'])->toBeNull();
    expect($result['ok'])->toBeTrue();
});

it('flags a misspelled field once the output shape is known from pinned data', function () {
    $graph = [
        'nodes' => [
            ['key' => 'a', 'type' => 'run_code', 'config' => [
                'operations' => [['op' => 'set', 'output' => 'result', 'value' => '1']],
            ], 'pinned_data' => ['result' => 'anything', 'meta' => ['count' => 3]]],
            ['key' => 'b', 'type' => 'run_code', 'config' => [
                'operations' => [
                    ['op' => 'set', 'output' => 'ok', 'value' => '{{ nodes.a.result }}'],
                    ['op' => 'set', 'output' => 'nested', 'value' => '{{ nodes.a.meta.count }}'],
                    ['op' => 'set', 'output' => 'missing', 'value' => '{{ nodes.a.does_not_exist }}'],
                ],
            ]],
        ],
        'edges' => [
            ['from' => 'a', 'to' => 'b', 'condition' => null],
        ],
    ];

    $result = app(DryRunner::class)->run($graph);

    expect($result['warnings'])->toHaveCount(1);
    expect($result['warnings'][0])->toContain('nodes.a.does_not_exist')->toContain('Known fields: result, meta');
    expect($result['unverified'])->toBe([]);
    // The simulated output carries placeholders, never the pinned values.
    expect($result['steps'][0]['sample_output'])->toBe(['result' => '<string>', 'meta' => ['count' => 0]]);
    expect($result['ok'])->toBeFalse();
});

it('flags a reference to a node that runs later', function () {
    $graph = [
        'nodes' => [
            ['key' => 'a', 'type' => 'run_code', 'config' => [
                'operations' => [['op' => 'set', 'output' => 'early', 'value' => '{{ nodes.b.result }}']],
            ]],
            ['key' => 'b', 'type' => 'run_code', 'config' => [
                'operations' => [['op' => 'set', 'output' => 'result', 'value' => '1']],
            ]],
        ],
        'edges' => [
            ['from' => 'a', 'to' => 'b', 'condition' => null],
        ],
    ];

    $result = app(DryRunner::class)->run($graph);

    expect($result['warnings'])->toHaveCount(1);
    expect($result['warnings'][0])->toContain('nothing provides');
});

it('short-circuits to issues without simulating an invalid graph', function () {
    $graph = [
        'nodes' => [
            ['key' => 'a', 'type' => 'run_code', 'config' => ['operations' => []]],
            ['key' => 'a', 'type' => 'run_code', 'config' => ['operations' => []]],
        ],
        'edges' => [],
    ];

    $result = app(DryRunner::class)->run($graph);

    expect($result['ok'])->toBeFalse();
    expect($result['issues'])->not->toBe([]);
    expect($result['steps'])->toBe([]);
});

it('warns when a failure path forgot to drop the always-edge it replaced', function () {
    $graph = [
        'nodes' => [
            ['key' => 'post', 'type' => 'run_code', 'config' => ['operations' => []]],
            ['key' => 'fallback', 'type' => 'run_code', 'config' => ['operations' => []]],
        ],
        'edges' => [
            ['from' => 'post', 'to' => 'fallback', 'condition' => null],
            ['from' => 'post', 'to' => 'fallback', 'condition' => 'error'],
        ],
    ];

    $result = app(DryRunner::class)->run($graph);

    expect($result['ok'])->toBeFalse();
    expect($result['warnings'])->toHaveCount(1);
    expect($result['warnings'][0])->toContain('both always and on error');
});

it('places each finding on its step and field for the editor', function () {
    $graph = [
        'nodes' => [
            ['key' => 'a', 'type' => 'run_code', 'config' => [
                'operations' => [['op' => 'set', 'output' => 'result', 'value' => '1']],
            ], 'pinned_data' => ['result' => 'anything']],
            ['key' => 'b', 'type' => 'run_code', 'config' => [
                'operations' => [
                    ['op' => 'set', 'output' => 'typo', 'value' => '{{ nodes.a.reslt }}'],
                    ['op' => 'set', 'output' => 'later', 'value' => '{{ nodes.c.result }}'],
                ],
            ]],
            ['key' => 'c', 'type' => 'run_code', 'config' => [
                'operations' => [['op' => 'set', 'output' => 'result', 'value' => '1']],
            ]],
        ],
        'edges' => [
            ['from' => 'a', 'to' => 'b', 'condition' => null],
            ['from' => 'b', 'to' => 'c', 'condition' => null],
            ['from' => 'b', 'to' => 'c', 'condition' => 'error'],
        ],
    ];

    $result = app(DryRunner::class)->run($graph);

    expect($result['findings'])->toBe([
        ['node_key' => 'b', 'field' => null, 'message' => 'Connects to "c" both always and on error, so "c" also runs when this step succeeds.'],
        ['node_key' => 'b', 'field' => 'operations', 'message' => 'Uses {{ nodes.a.reslt }}, but step "a" doesn\'t output that.'],
        ['node_key' => 'b', 'field' => 'operations', 'message' => 'Uses {{ nodes.c.result }}, but no step before this one provides it.'],
    ]);
    expect($result['warnings'])->toHaveCount(3);
});
