<?php

use App\Exceptions\DraftConflictException;
use App\Models\WorkflowBuilderDraftVersion;
use App\Models\WorkflowBuilderSession;
use App\Services\WorkflowBuilder\DraftService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlanSeeder::class);
    $this->service = app(DraftService::class);
});

// ─── addNode ──────────────────────────────────────────────────────────────────

test('addNode persists the node to the draft', function () {
    $session = WorkflowBuilderSession::factory()->create();

    $this->service->addNode($session, [
        'id' => 'n1',
        'type' => 'webhook_trigger',
        'name' => 'Webhook',
        'config' => [],
        'position' => ['x' => 0, 'y' => 0],
    ]);

    $nodes = $session->fresh()->nodes_draft;

    expect($nodes)->toHaveCount(1)
        ->and($nodes[0]['id'])->toBe('n1');
});

test('addNode creates a snapshot after mutation', function () {
    $session = WorkflowBuilderSession::factory()->create();

    $this->service->addNode($session, [
        'id' => 'n1',
        'type' => 'transform',
        'name' => 'Action',
        'config' => [],
        'position' => ['x' => 0, 'y' => 0],
    ]);

    expect(WorkflowBuilderDraftVersion::where('session_id', $session->id)->count())->toBe(1);
});

test('addNode increments draft_lock_version', function () {
    $session = WorkflowBuilderSession::factory()->create(['draft_lock_version' => 0]);

    $this->service->addNode($session, [
        'id' => 'n1',
        'type' => 'transform',
        'name' => 'Action',
        'config' => [],
        'position' => ['x' => 0, 'y' => 0],
    ]);

    expect($session->fresh()->draft_lock_version)->toBe(1);
});

// ─── removeNode ───────────────────────────────────────────────────────────────

test('removeNode deletes the node from the draft', function () {
    $session = WorkflowBuilderSession::factory()->withNodes([
        ['id' => 'n1', 'type' => 'webhook_trigger', 'name' => 'Webhook', 'config' => [], 'position' => []],
        ['id' => 'n2', 'type' => 'transform', 'name' => 'Action', 'config' => [], 'position' => []],
    ])->create();

    $this->service->removeNode($session, 'n1');

    $nodes = $session->fresh()->nodes_draft;
    $ids = array_column($nodes, 'id');

    expect($nodes)->toHaveCount(1)
        ->and($ids)->not->toContain('n1');
});

test('removeNode also removes edges connected to that node', function () {
    $session = WorkflowBuilderSession::factory()->withNodes(
        [
            ['id' => 'n1', 'type' => 'webhook_trigger', 'name' => 'Trigger', 'config' => [], 'position' => []],
            ['id' => 'n2', 'type' => 'transform', 'name' => 'Action', 'config' => [], 'position' => []],
            ['id' => 'n3', 'type' => 'transform', 'name' => 'Other', 'config' => [], 'position' => []],
        ],
        [
            ['source' => 'n1', 'target' => 'n2', 'sourceHandle' => 'output', 'targetHandle' => 'input'],
            ['source' => 'n2', 'target' => 'n3', 'sourceHandle' => 'output', 'targetHandle' => 'input'],
        ]
    )->create();

    $this->service->removeNode($session, 'n2');

    expect($session->fresh()->edges_draft)->toBeEmpty();
});

// ─── updateNode ───────────────────────────────────────────────────────────────

test('updateNode deep-merges nested config without overwriting existing keys', function () {
    $session = WorkflowBuilderSession::factory()->withNodes([
        [
            'id' => 'n1',
            'type' => 'http',
            'name' => 'HTTP',
            'config' => ['method' => 'GET', 'headers' => ['Accept' => 'application/json']],
            'position' => ['x' => 0, 'y' => 0],
        ],
    ])->create();

    $this->service->updateNode($session, 'n1', [
        'config' => ['url' => 'https://example.com'],
    ]);

    $node = collect($session->fresh()->nodes_draft)->firstWhere('id', 'n1');

    expect($node['config']['method'])->toBe('GET')
        ->and($node['config']['url'])->toBe('https://example.com');
});

test('updateNode returns false when node does not exist', function () {
    $session = WorkflowBuilderSession::factory()->create();

    $result = $this->service->updateNode($session, 'nonexistent', ['name' => 'X']);

    expect($result)->toBeFalse();
});

// ─── Optimistic locking ───────────────────────────────────────────────────────

test('mutation throws DraftConflictException when lock version is stale', function () {
    $session = WorkflowBuilderSession::factory()->create(['draft_lock_version' => 0]);

    // Simulate another process incrementing the version
    WorkflowBuilderSession::whereKey($session->id)->update(['draft_lock_version' => 1]);

    // Our $session still thinks version is 0 → stale
    $this->service->addNode($session, [
        'id' => 'n1',
        'type' => 'transform',
        'name' => 'Action',
        'config' => [],
        'position' => ['x' => 0, 'y' => 0],
    ]);
})->throws(DraftConflictException::class);

// ─── applyBulk ────────────────────────────────────────────────────────────────

test('applyBulk replaces all nodes and edges at once', function () {
    $session = WorkflowBuilderSession::factory()->withNodes([
        ['id' => 'old_node', 'type' => 'transform', 'name' => 'Old', 'config' => [], 'position' => []],
    ])->create();

    $newNodes = [
        ['id' => 'n1', 'type' => 'webhook_trigger', 'name' => 'Webhook', 'config' => [], 'position' => ['x' => 0, 'y' => 0]],
        ['id' => 'n2', 'type' => 'transform', 'name' => 'Action', 'config' => [], 'position' => ['x' => 250, 'y' => 0]],
    ];
    $newEdges = [
        ['source' => 'n1', 'target' => 'n2', 'sourceHandle' => 'output', 'targetHandle' => 'input'],
    ];

    $this->service->applyBulk($session, $newNodes, $newEdges);

    $fresh = $session->fresh();
    expect($fresh->nodes_draft)->toHaveCount(2)
        ->and($fresh->edges_draft)->toHaveCount(1)
        ->and(collect($fresh->nodes_draft)->pluck('id')->all())->not->toContain('old_node');
});
