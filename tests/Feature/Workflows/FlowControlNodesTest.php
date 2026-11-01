<?php

use App\Enums\NodeRunStatus;

/* ------------------------------------------------------------------ switch */

it('sends the run down the branch of the first matching case', function (string $status, string $branch, bool $matched) {
    $output = executeNode('switch', ['value' => $status, 'cases' => ['new' => 'welcome', 'active' => 'keep', 'cancelled' => 'win-back'], 'default_branch' => 'other']);

    expect($output)->toMatchArray(['result' => $branch, 'matched' => $matched]);
})->with([
    'first' => ['new', 'welcome', true],
    'last' => ['cancelled', 'win-back', true],
    'ignores capitals by default' => ['ACTIVE', 'keep', true],
    'nothing matches' => ['paused', 'other', false],
    'empty value' => ['', 'other', false],
]);

it('calls the default branch "default" unless told otherwise', function () {
    expect(executeNode('switch', ['value' => 'x', 'cases' => ['a' => 'one']])['result'])->toBe('default')
        ->and(executeNode('switch', ['value' => 'x', 'cases' => ['a' => 'one'], 'default_branch' => '  '])['result'])->toBe('default');
});

it('takes the first of several cases that match, and can send two cases the same way', function () {
    $both = executeNode('switch', ['value' => 'gold', 'cases' => ['silver' => 'paid', 'gold' => 'paid', 'free' => 'free']]);
    $order = executeNode('switch', ['value' => 'pro plan', 'match' => 'contains', 'cases' => ['pro' => 'first', 'plan' => 'second']]);

    expect($both)->toMatchArray(['result' => 'paid', 'case' => 'gold'])
        ->and($order)->toMatchArray(['result' => 'first', 'case' => 'pro']);
});

it('compares numbers by value and respects capital letters when asked', function () {
    expect(executeNode('switch', ['value' => '5.0', 'cases' => ['5' => 'five']])['result'])->toBe('five')
        ->and(executeNode('switch', ['value' => 5, 'cases' => ['05' => 'five']])['result'])->toBe('five')
        ->and(executeNode('switch', ['value' => 'US', 'cases' => ['us' => 'north-america'], 'ignore_case' => false])['matched'])->toBeFalse();
});

it('can match by contains, starts with, ends with and one of a list', function (string $match, string $value, string $case, bool $expected) {
    expect(executeNode('switch', ['value' => $value, 'match' => $match, 'cases' => [$case => 'hit']])['matched'])->toBe($expected);
})->with([
    'contains' => ['contains', 'invoice-2043-final', '2043', true],
    'does not contain' => ['contains', 'invoice-2043-final', '9999', false],
    'starts with' => ['starts_with', 'invoice-2043', 'invoice', true],
    'ends with' => ['ends_with', 'invoice-2043', '2043', true],
    'ends with, no' => ['ends_with', 'invoice-2043', 'invoice', false],
    'in list' => ['in_list', 'GB', 'US, GB, DE', true],
    'in list, no' => ['in_list', 'FR', 'US, GB, DE', false],
]);

it('refuses a switch with no cases, a case with no branch and a match it does not know', function () {
    expect(fn () => executeNode('switch', ['value' => 'x', 'cases' => []]))->toThrow(InvalidArgumentException::class, 'at least one case')
        ->and(fn () => executeNode('switch', ['value' => 'x', 'cases' => ['a' => ' ']]))->toThrow(InvalidArgumentException::class, 'no branch')
        ->and(fn () => executeNode('switch', ['value' => 'x', 'cases' => ['a' => 'b'], 'match' => 'fuzzy']))->toThrow(InvalidArgumentException::class, 'Unsupported match')
        ->and(fn () => executeNode('switch', ['value' => 'x', 'cases' => array_combine(range(1, 101), range(1, 101))]))->toThrow(InvalidArgumentException::class, 'at most 100');
});

it('runs only the branch its value picks and skips the rest', function () {
    $graph = [
        'nodes' => [
            ['key' => 'pick', 'type' => 'switch', 'config' => ['value' => '{{ input.plan }}', 'cases' => ['free' => 'basic', 'pro' => 'premium', 'team' => 'premium'], 'default_branch' => 'other']],
            ['key' => 'basic', 'type' => 'set_variable', 'config' => ['variables' => ['seen' => 'basic']]],
            ['key' => 'premium', 'type' => 'set_variable', 'config' => ['variables' => ['seen' => 'premium']]],
            ['key' => 'other', 'type' => 'set_variable', 'config' => ['variables' => ['seen' => 'other']]],
        ],
        'edges' => [
            ['from' => 'pick', 'to' => 'basic', 'condition' => 'basic'],
            ['from' => 'pick', 'to' => 'premium', 'condition' => 'premium'],
            ['from' => 'pick', 'to' => 'other', 'condition' => 'other'],
        ],
    ];

    $statuses = fn (string $plan): array => runGraph($graph, ['plan' => $plan])->nodeRuns->mapWithKeys(fn ($nodeRun): array => [$nodeRun->key => $nodeRun->status])->all();

    expect($statuses('pro'))->toMatchArray(['basic' => NodeRunStatus::Skipped, 'premium' => NodeRunStatus::Completed, 'other' => NodeRunStatus::Skipped])
        ->and($statuses('team'))->toMatchArray(['premium' => NodeRunStatus::Completed])
        ->and($statuses('free'))->toMatchArray(['basic' => NodeRunStatus::Completed, 'premium' => NodeRunStatus::Skipped])
        ->and($statuses('mystery'))->toMatchArray(['other' => NodeRunStatus::Completed, 'basic' => NodeRunStatus::Skipped, 'premium' => NodeRunStatus::Skipped]);
});

/* ------------------------------------------------------------------- batch */

it('splits a list into batches, the last one shorter', function () {
    $output = executeNode('batch', ['items' => range(1, 7), 'size' => 3]);

    expect($output)->toMatchArray(['batches' => [[1, 2, 3], [4, 5, 6], [7]], 'batch_count' => 3, 'item_count' => 7, 'has_items' => true]);
});

it('gives one batch for a short list and none for an empty one', function () {
    expect(executeNode('batch', ['items' => ['a', 'b'], 'size' => 50])['batches'])->toBe([['a', 'b']])
        ->and(executeNode('batch', ['items' => [], 'size' => 10]))->toMatchArray(['batches' => [], 'batch_count' => 0, 'item_count' => 0, 'has_items' => false]);
});

it('keeps the items whole, including rows, and reads JSON text as a list', function () {
    $rows = [['id' => 1], ['id' => 2], ['id' => 3]];

    expect(executeNode('batch', ['items' => $rows, 'size' => 2])['batches'])->toBe([[['id' => 1], ['id' => 2]], [['id' => 3]]])
        ->and(executeNode('batch', ['items' => '[1,2,3,4]', 'size' => '2'])['batches'])->toBe([[1, 2], [3, 4]]);
});

it('refuses what is not a list, a size below one and a split that would make too many batches', function () {
    expect(fn () => executeNode('batch', ['items' => 'not a list', 'size' => 2]))->toThrow(InvalidArgumentException::class, 'must be a list')
        ->and(fn () => executeNode('batch', ['items' => [1, 2], 'size' => 0]))->toThrow(InvalidArgumentException::class, 'at least 1')
        ->and(fn () => executeNode('batch', ['items' => [1, 2], 'size' => 'many']))->toThrow(InvalidArgumentException::class, 'at least 1')
        ->and(fn () => executeNode('batch', ['items' => range(1, 1001), 'size' => 1]))->toThrow(InvalidArgumentException::class, 'more than 1,000 batches');
});

it('hands each batch to a step that runs for each one', function () {
    $run = runGraph(['nodes' => [
        ['key' => 'split', 'type' => 'batch', 'config' => ['items' => '{{ input.emails }}', 'size' => 2]],
        ['key' => 'count', 'type' => 'array', 'config' => ['operation' => 'length', 'items' => '{{ nodes.split.batches }}']],
    ], 'edges' => [['from' => 'split', 'to' => 'count', 'condition' => null]]], ['emails' => ['a', 'b', 'c', 'd', 'e']]);

    expect($run->nodeRuns->firstWhere('key', 'count')->output['result'])->toBe(3);
});

/* ----------------------------------------------------------- merge results */

/**
 * Two branches that each make a value, joined, then a step that uses them.
 *
 * @param  array<string, mixed>  $joinConfig
 * @return array<string, mixed>
 */
function twoBranchesJoined(array $joinConfig): array
{
    return [
        'nodes' => [
            ['key' => 'a', 'type' => 'set_variable', 'config' => ['variables' => ['name' => 'alpha', 'n' => 1]]],
            ['key' => 'b', 'type' => 'set_variable', 'config' => ['variables' => ['name' => 'beta', 'n' => 2]]],
            ['key' => 'merge', 'type' => 'join_paths', 'config' => $joinConfig],
            ['key' => 'after', 'type' => 'set_variable', 'config' => ['variables' => ['first' => '{{ nodes.merge.results.a.name }}', 'second' => '{{ nodes.merge.results.b.name }}']]],
        ],
        'edges' => [
            ['from' => 'a', 'to' => 'merge', 'condition' => null],
            ['from' => 'b', 'to' => 'merge', 'condition' => null],
            ['from' => 'merge', 'to' => 'after', 'condition' => null],
        ],
    ];
}

it('hands on what each branch produced, by step name', function () {
    $run = runGraph(twoBranchesJoined(['combine' => 'by_step']));
    $merge = $run->nodeRuns->firstWhere('key', 'merge');

    expect($merge->output)->toMatchArray([
        'results' => ['a' => ['name' => 'alpha', 'n' => 1], 'b' => ['name' => 'beta', 'n' => 2]],
        'count' => 2,
        'arrived' => ['a', 'b'],
        'skipped' => [],
        'failed' => [],
        'omitted' => [],
    ])
        ->and($run->nodeRuns->firstWhere('key', 'after')->output)->toMatchArray(['first' => 'alpha', 'second' => 'beta']);
});

it('can hand the results on as a list, in the order the branches are connected', function () {
    $merge = runGraph(twoBranchesJoined(['combine' => 'list']))->nodeRuns->firstWhere('key', 'merge');

    expect($merge->output['results'])->toBe([['name' => 'alpha', 'n' => 1], ['name' => 'beta', 'n' => 2]]);
});

it('still just waits, returning nothing, when a join has no setting, as every join did before', function () {
    $merge = runGraph(twoBranchesJoined([]))->nodeRuns->firstWhere('key', 'merge');

    expect($merge->status)->toBe(NodeRunStatus::Completed)
        ->and($merge->output)->toBe([]);
});

it('names the branches that did not run, so the next step knows what it is missing', function () {
    $run = runGraph([
        'nodes' => [
            ['key' => 'pick', 'type' => 'switch', 'config' => ['value' => '{{ input.plan }}', 'cases' => ['pro' => 'a', 'free' => 'b']]],
            ['key' => 'a', 'type' => 'set_variable', 'config' => ['variables' => ['name' => 'alpha']]],
            ['key' => 'b', 'type' => 'set_variable', 'config' => ['variables' => ['name' => 'beta']]],
            ['key' => 'merge', 'type' => 'join_paths', 'config' => ['combine' => 'by_step']],
        ],
        'edges' => [
            ['from' => 'pick', 'to' => 'a', 'condition' => 'a'],
            ['from' => 'pick', 'to' => 'b', 'condition' => 'b'],
            ['from' => 'a', 'to' => 'merge', 'condition' => null],
            ['from' => 'b', 'to' => 'merge', 'condition' => null],
        ],
    ], ['plan' => 'pro']);

    $merge = $run->nodeRuns->firstWhere('key', 'merge');

    expect($merge->status)->toBe(NodeRunStatus::Completed)
        ->and($merge->output)->toMatchArray(['results' => ['a' => ['name' => 'alpha']], 'count' => 1, 'arrived' => ['a'], 'skipped' => ['b']]);
});

it('leaves out a result too large to carry, and says which', function () {
    $big = str_repeat('x', 600000);
    $graph = twoBranchesJoined(['combine' => 'by_step']);
    $graph['nodes'][0]['config']['variables'] = ['blob' => $big];
    $graph['nodes'][1]['config']['variables'] = ['blob' => $big];

    $merge = runGraph($graph)->nodeRuns->firstWhere('key', 'merge');

    expect($merge->output['arrived'])->toBe(['a', 'b'])
        ->and(array_keys($merge->output['results']))->toBe(['a'])
        ->and($merge->output['omitted'])->toBe(['b']);
});
