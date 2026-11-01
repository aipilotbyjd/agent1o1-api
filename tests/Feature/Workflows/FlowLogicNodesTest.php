<?php

use App\Models\Runs\Run;
use App\Nodes\FlowLogic\FilterNode;
use App\Nodes\FlowLogic\RouterNode;

it('routes to the first matching condition, falling back to default', function () {
    $node = new RouterNode;
    $run = Run::factory()->create();

    $config = [
        'conditions' => [
            ['path' => 'input.status', 'operator' => 'equals', 'value' => 'open', 'result' => 'is_open'],
            ['path' => 'input.status', 'operator' => 'equals', 'value' => 'closed', 'result' => 'is_closed'],
        ],
    ];

    expect($node->execute($run, $config, ['input' => ['status' => 'closed']]))->toBe(['result' => 'is_closed']);
    expect($node->execute($run, $config, ['input' => ['status' => 'unknown']]))->toBe(['result' => 'default']);
});

it('gates on a single condition as passed/failed', function () {
    $node = new FilterNode;
    $run = Run::factory()->create();

    $config = ['path' => 'input.count', 'operator' => 'greater_than', 'value' => 5];

    expect($node->execute($run, $config, ['input' => ['count' => 10]]))
        ->toBe(['result' => 'passed', 'passed' => true]);

    expect($node->execute($run, $config, ['input' => ['count' => 1]]))
        ->toBe(['result' => 'failed', 'passed' => false]);
});

it('routes on operators the filter also understands, with no compare-to value', function () {
    $node = new RouterNode;
    $run = Run::factory()->create();

    $config = [
        'conditions' => [
            ['path' => 'input.email', 'operator' => 'is_empty', 'result' => 'missing'],
            ['path' => 'input.email', 'operator' => 'ends_with', 'value' => '@acme.com', 'result' => 'internal'],
        ],
    ];

    expect($node->execute($run, $config, ['input' => ['email' => '']]))->toBe(['result' => 'missing'])
        ->and($node->execute($run, $config, ['input' => ['email' => 'a@acme.com']]))->toBe(['result' => 'internal'])
        ->and($node->execute($run, $config, ['input' => ['email' => 'a@other.com']]))->toBe(['result' => 'default']);
});

it('treats a zero as a value rather than empty when filtering', function () {
    $node = new FilterNode;
    $run = Run::factory()->create();

    expect($node->execute($run, ['path' => 'input.count', 'operator' => 'is_not_empty'], ['input' => ['count' => 0]])['passed'])->toBeTrue();
});

it('fails loudly on an unknown operator instead of passing', function () {
    (new FilterNode)->execute(Run::factory()->create(), ['path' => 'input.a', 'operator' => 'nope'], ['input' => ['a' => 1]]);
})->throws(InvalidArgumentException::class);

it('caps a delay at seven days and never goes negative', function () {
    $node = new App\Nodes\FlowLogic\DelayNode;
    $run = Run::factory()->create();

    expect($node->execute($run, ['seconds' => 99999999], []))->toBe(['seconds' => 604800])
        ->and($node->execute($run, ['seconds' => -5], []))->toBe(['seconds' => 0])
        ->and($node->execute($run, [], []))->toBe(['seconds' => 0]);
});
