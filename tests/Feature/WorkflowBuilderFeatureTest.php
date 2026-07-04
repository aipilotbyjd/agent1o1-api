<?php

use App\Enums\BuilderSessionStatus;
use App\Enums\Role;
use App\Models\User;
use App\Models\WorkflowBuilderSession;
use App\Models\Workspace;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(PlanSeeder::class);

    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['owner_id' => $this->user->id]);
    $this->workspace->members()->attach($this->user->id, [
        'id' => Str::uuid()->toString(),
        'role' => Role::Owner,
        'joined_at' => now(),
    ]);
});

// ─── Sessions ────────────────────────────────────────────────────────────────

test('a user can create a builder session', function () {
    $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/workflow-builder/sessions", [
            'title' => 'My automation',
        ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'My automation')
        ->assertJsonPath('data.status', 'active');

    expect(WorkflowBuilderSession::count())->toBe(1);
});

test('a session stores a user message when created with a prompt', function () {
    // This test verifies the session and message are persisted without hitting the AI.
    $session = WorkflowBuilderSession::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'title' => 'Webhook to Slack',
    ]);
    $session->messages()->create(['role' => 'user', 'content' => 'I want to send a Slack message when a webhook arrives']);

    expect($session->messages()->where('role', 'user')->count())->toBe(1);
});

test('a user can list their active sessions', function () {
    WorkflowBuilderSession::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'title' => 'Session A',
    ]);

    WorkflowBuilderSession::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'title' => 'Session B',
        'status' => 'completed',
    ]);

    $this->actingAs($this->user, 'api')
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/workflow-builder/sessions")
        ->assertOk()
        ->assertJsonPath('pagination.total', 1);
});

test('a user can retrieve a session with its messages', function () {
    $session = WorkflowBuilderSession::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'title' => 'My session',
    ]);

    $session->messages()->create(['role' => 'user', 'content' => 'Hello']);
    $session->messages()->create(['role' => 'assistant', 'content' => 'Hi!']);

    $this->actingAs($this->user, 'api')
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/workflow-builder/sessions/{$session->id}")
        ->assertOk()
        ->assertJsonPath('data.title', 'My session')
        ->assertJsonCount(2, 'data.messages');
});

test('another user cannot access a session', function () {
    $other = User::factory()->create();
    $session = WorkflowBuilderSession::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $other->id,
        'title' => 'Private session',
    ]);

    $this->actingAs($this->user, 'api')
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/workflow-builder/sessions/{$session->id}")
        ->assertStatus(404);
});

test('a user can delete their session', function () {
    $session = WorkflowBuilderSession::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'title' => 'Delete me',
    ]);

    $this->actingAs($this->user, 'api')
        ->deleteJson("/api/v1/workspaces/{$this->workspace->id}/workflow-builder/sessions/{$session->id}")
        ->assertOk();

    // Discarding archives the session (kept out of active lists), not hard-deletes it.
    expect($session->fresh()->status)->toBe(BuilderSessionStatus::Archived)
        ->and(WorkflowBuilderSession::active()->count())->toBe(0);
});

// ─── Session draft manipulation ───────────────────────────────────────────────

test('adding a node to the session draft is reflected in the response', function () {
    $session = WorkflowBuilderSession::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'title' => 'Draft test',
    ]);

    $session->addNode(['id' => 'node_1', 'type' => 'trigger', 'name' => 'Webhook', 'config' => [], 'position' => ['x' => 0, 'y' => 200]]);
    $session->addNode(['id' => 'node_2', 'type' => 'http_request', 'name' => 'HTTP', 'config' => [], 'position' => ['x' => 250, 'y' => 200]]);
    $session->addEdge(['source' => 'node_1', 'target' => 'node_2', 'sourceHandle' => 'output', 'targetHandle' => 'input']);

    $session->refresh();
    expect($session->nodes_draft)->toHaveCount(2)
        ->and($session->edges_draft)->toHaveCount(1);
});

test('removing a node also removes its connected edges', function () {
    $session = WorkflowBuilderSession::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'title' => 'Remove test',
        'nodes_draft' => [
            ['id' => 'node_a', 'type' => 'trigger', 'name' => 'Trigger', 'config' => [], 'position' => ['x' => 0, 'y' => 0]],
            ['id' => 'node_b', 'type' => 'transform', 'name' => 'Transform', 'config' => [], 'position' => ['x' => 250, 'y' => 0]],
        ],
        'edges_draft' => [
            ['source' => 'node_a', 'target' => 'node_b', 'sourceHandle' => 'output', 'targetHandle' => 'input'],
        ],
    ]);

    $session->removeNode('node_b');
    $session->refresh();

    expect($session->nodes_draft)->toHaveCount(1)
        ->and($session->edges_draft)->toHaveCount(0);
});

test('updating a node merges config fields', function () {
    $session = WorkflowBuilderSession::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'title' => 'Update test',
        'nodes_draft' => [
            ['id' => 'node_x', 'type' => 'transform', 'name' => 'Old name', 'config' => ['field_a' => 1], 'position' => ['x' => 0, 'y' => 0]],
        ],
    ]);

    $session->updateNode('node_x', ['name' => 'New name']);
    $session->refresh();

    expect($session->nodes_draft[0]['name'])->toBe('New name')
        ->and($session->nodes_draft[0]['config']['field_a'])->toBe(1);
});

// ─── Builder session save ─────────────────────────────────────────────────────

test('saving a session creates a workflow', function () {
    $session = WorkflowBuilderSession::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'title' => 'Save me',
        'nodes_draft' => [['id' => 'n1', 'type' => 'trigger', 'name' => 'Start', 'config' => [], 'position' => ['x' => 0, 'y' => 0]]],
        'edges_draft' => [],
    ]);

    $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/workflow-builder/sessions/{$session->id}/save")
        ->assertCreated()
        ->assertJsonPath('data.name', 'Save me');

    $session->refresh();
    expect($session->status)->toBe(BuilderSessionStatus::Completed)
        ->and($session->workflow_id)->not->toBeNull();
});

// ─── Unauthenticated access ───────────────────────────────────────────────────

test('unauthenticated users cannot create sessions', function () {
    $this->postJson("/api/v1/workspaces/{$this->workspace->id}/workflow-builder/sessions", [
        'title' => 'Should fail',
    ])->assertUnauthorized();
});
