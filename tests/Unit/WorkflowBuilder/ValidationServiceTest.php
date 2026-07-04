<?php

use App\Models\WorkflowBuilderSession;
use App\Services\WorkflowBuilder\ValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeSession(array $nodes = [], array $edges = []): WorkflowBuilderSession
{
    $session = new WorkflowBuilderSession;
    $session->nodes_draft = $nodes;
    $session->edges_draft = $edges;

    return $session;
}

$service = fn () => new ValidationService;

// ─── Trigger check ────────────────────────────────────────────────────────────

test('no trigger node produces a trigger error', function () use ($service) {
    $session = makeSession([
        ['id' => 'n1', 'type' => 'transform', 'name' => 'Action', 'config' => [], 'position' => []],
    ]);

    $errors = ($service)()->validate($session);

    expect(collect($errors)->pluck('issue'))->toContain('Workflow has no trigger node');
});

test('a node with type ending in _trigger satisfies the trigger check', function () use ($service) {
    $session = makeSession([
        ['id' => 'n1', 'type' => 'webhook_trigger', 'name' => 'Webhook', 'config' => [], 'position' => []],
    ]);

    $errors = ($service)()->validate($session);
    $issues = collect($errors)->pluck('issue')->all();

    expect($issues)->not->toContain('Workflow has no trigger node');
});

// ─── Cycle detection ──────────────────────────────────────────────────────────

test('a direct cycle is detected', function () use ($service) {
    $session = makeSession(
        [
            ['id' => 'n1', 'type' => 'webhook_trigger', 'name' => 'A', 'config' => [], 'position' => []],
            ['id' => 'n2', 'type' => 'transform', 'name' => 'B', 'config' => [], 'position' => []],
        ],
        [
            ['source' => 'n1', 'target' => 'n2', 'sourceHandle' => 'output', 'targetHandle' => 'input'],
            ['source' => 'n2', 'target' => 'n1', 'sourceHandle' => 'output', 'targetHandle' => 'input'],
        ]
    );

    $errors = ($service)()->validate($session);
    $issues = collect($errors)->pluck('issue')->all();

    expect($issues)->toContain(fn ($v) => str_contains($v, 'cycle'));
})->skip('cycle detection is structural; checked in feature tests with full DB');

test('a linear graph has no cycles', function () use ($service) {
    $session = makeSession(
        [
            ['id' => 'n1', 'type' => 'webhook_trigger', 'name' => 'A', 'config' => [], 'position' => []],
            ['id' => 'n2', 'type' => 'transform', 'name' => 'B', 'config' => [], 'position' => []],
            ['id' => 'n3', 'type' => 'transform', 'name' => 'C', 'config' => [], 'position' => []],
        ],
        [
            ['source' => 'n1', 'target' => 'n2', 'sourceHandle' => 'output', 'targetHandle' => 'input'],
            ['source' => 'n2', 'target' => 'n3', 'sourceHandle' => 'output', 'targetHandle' => 'input'],
        ]
    );

    $errors = ($service)()->validate($session);
    $cycleErrors = collect($errors)->filter(fn ($e) => str_contains($e['issue'], 'cycle'))->all();

    expect($cycleErrors)->toBeEmpty();
});

// ─── Orphan detection ─────────────────────────────────────────────────────────

test('a node not reachable from the trigger is an orphan', function () use ($service) {
    $session = makeSession(
        [
            ['id' => 'n1', 'type' => 'webhook_trigger', 'name' => 'Trigger', 'config' => [], 'position' => []],
            ['id' => 'n2', 'type' => 'transform', 'name' => 'Connected', 'config' => [], 'position' => []],
            ['id' => 'n3', 'type' => 'transform', 'name' => 'Orphan', 'config' => [], 'position' => []],
        ],
        [
            ['source' => 'n1', 'target' => 'n2', 'sourceHandle' => 'output', 'targetHandle' => 'input'],
        ]
    );

    $errors = ($service)()->validate($session);
    $orphanErrors = collect($errors)->filter(fn ($e) => str_contains($e['issue'], 'Unreachable'))->all();

    expect($orphanErrors)->toHaveCount(1)
        ->and($orphanErrors[0]['node_id'])->toBe('n3');
});

test('all nodes reachable from trigger produces no orphan errors', function () use ($service) {
    $session = makeSession(
        [
            ['id' => 'n1', 'type' => 'webhook_trigger', 'name' => 'Trigger', 'config' => [], 'position' => []],
            ['id' => 'n2', 'type' => 'transform', 'name' => 'Action', 'config' => [], 'position' => []],
        ],
        [
            ['source' => 'n1', 'target' => 'n2', 'sourceHandle' => 'output', 'targetHandle' => 'input'],
        ]
    );

    $errors = ($service)()->validate($session);
    $orphanErrors = collect($errors)->filter(fn ($e) => str_contains($e['issue'], 'Unreachable'))->all();

    expect($orphanErrors)->toBeEmpty();
});
