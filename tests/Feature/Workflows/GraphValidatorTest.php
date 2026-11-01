<?php

use App\Services\Workflows\GraphValidator;

function node(string $key, string $type = 'transform', ?array $config = null): array
{
    return ['key' => $key, 'type' => $type, 'config' => $config ?? ['mapping' => []]];
}

function edge(string $from, string $to, ?string $condition = null): array
{
    return ['from' => $from, 'to' => $to, 'condition' => $condition];
}

it('accepts a valid linear graph', function () {
    $errors = app(GraphValidator::class)->validate(
        [node('a', 'transform', ['mapping' => []]), node('b', 'transform', ['mapping' => []])],
        [edge('a', 'b')],
    );

    expect($errors)->toBe([]);
});

it('rejects a duplicate node key', function () {
    $errors = app(GraphValidator::class)->validate(
        [node('a'), node('a')],
        [],
    );

    expect($errors)->toBe(["Duplicate node key 'a'."]);
});

it('rejects a dangling edge', function () {
    $errors = app(GraphValidator::class)->validate(
        [node('a')],
        [edge('a', 'ghost')],
    );

    expect($errors)->toBe(["Edge references unknown target node 'ghost'."]);
});

it('rejects a cycle', function () {
    $errors = app(GraphValidator::class)->validate(
        [node('a'), node('b')],
        [edge('a', 'b'), edge('b', 'a')],
    );

    expect($errors)->toBe(['The graph contains a cycle.']);
});

it('rejects an empty graph', function () {
    $errors = app(GraphValidator::class)->validate([], []);

    expect($errors)->toBe(['The workflow has no nodes. Add at least one node before publishing.']);
});

it('treats a node with no incoming edge as its own entry rather than unreachable', function () {
    // "Entry" here means structurally zero-incoming (no dedicated trigger
    // node type exists yet: see docs/WORKFLOWS_AGENTS_BUILD_PLAN.md Stage
    // 4/11), so a disconnected node is a second valid entry point, not dead.
    // `unreachableNodeErrors()` stays in place for when entries narrow to
    // "trigger-type nodes only" and a structurally-orphaned node should
    // become a real error instead.
    $errors = app(GraphValidator::class)->validate(
        [node('a'), node('b'), node('separate_entry')],
        [edge('a', 'b')],
    );

    expect($errors)->toBe([]);
});

it('does not traverse error-only edges for cycle/reachability checks', function () {
    $errors = app(GraphValidator::class)->validate(
        [node('a'), node('recovery')],
        [edge('a', 'recovery', 'error')],
    );

    // 'recovery' is only reachable via an error edge, so from a healthy-path
    // perspective it has no incoming edge and becomes its own entry node,
    // not unreachable, not a false-positive "no entry node" failure either.
    expect($errors)->toBe([]);
});

it('surfaces per-node config schema errors once the graph shape is valid', function () {
    $errors = app(GraphValidator::class)->validate(
        [node('a', 'call_api', ['url' => 'https://example.com'])],
        [],
    );

    expect($errors)->toBe(["Node 'a': config.method is required."]);
});

it('skips config validation for a node type not yet in the registry', function () {
    $errors = app(GraphValidator::class)->validate(
        [node('a', 'human_approval', [])],
        [],
    );

    expect($errors)->toBe([]);
});

it('rejects a node type that does not exist, but lets a custom node through', function () {
    $errors = app(GraphValidator::class)->validate(
        [node('a', 'trasnform', []), node('b', 'custom:0197a4c2-0000-7000-8000-000000000000', [])],
        [edge('a', 'b')],
    );

    expect($errors)->toBe(["Node 'a': there is no node type [trasnform]."]);
});
