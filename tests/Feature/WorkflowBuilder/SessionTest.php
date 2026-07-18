<?php

use App\Enums\BuilderSessionStatus;
use App\Enums\Role;
use App\Models\User;
use App\Models\WorkflowBuilderSession;
use App\Models\Workspace;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\Queue;
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

test('a user can create an active session', function () {
    $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/workflow-builder/sessions", [
            'title' => 'My automation',
        ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'My automation')
        ->assertJsonPath('data.status', 'active');

    expect(WorkflowBuilderSession::count())->toBe(1);
});

test('creating a session with a prompt returns 202 with message_id', function () {
    Queue::fake();

    $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/workflow-builder/sessions", [
            'title' => 'Test',
            'prompt' => 'Build me a webhook to Slack workflow',
        ])
        ->assertStatus(202)
        ->assertJsonStructure(['data' => ['session_id', 'message_id']]);
});

test('a user can list their active sessions', function () {
    WorkflowBuilderSession::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'title' => 'Session A',
    ]);

    WorkflowBuilderSession::factory()->completed()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'title' => 'Session B',
    ]);

    $this->actingAs($this->user, 'api')
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/workflow-builder/sessions")
        ->assertOk()
        ->assertJsonPath('pagination.total', 1);
});

test('listing sessions can be filtered by status', function () {
    WorkflowBuilderSession::factory()->completed()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $this->actingAs($this->user, 'api')
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/workflow-builder/sessions?status=completed")
        ->assertOk()
        ->assertJsonPath('pagination.total', 1);
});

test('a user can retrieve a session with its messages', function () {
    $session = WorkflowBuilderSession::factory()->create([
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

test('a user can rename an active session', function () {
    $session = WorkflowBuilderSession::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $this->actingAs($this->user, 'api')
        ->patchJson("/api/v1/workspaces/{$this->workspace->id}/workflow-builder/sessions/{$session->id}", [
            'title' => 'New Title',
        ])
        ->assertOk()
        ->assertJsonPath('data.title', 'New Title');
});

test('a user can discard (delete) a session', function () {
    $session = WorkflowBuilderSession::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $this->actingAs($this->user, 'api')
        ->deleteJson("/api/v1/workspaces/{$this->workspace->id}/workflow-builder/sessions/{$session->id}")
        ->assertOk();

    expect($session->fresh()->status)->toBe(BuilderSessionStatus::Archived);
});

test('another user cannot access a session', function () {
    $other = User::factory()->create();
    $session = WorkflowBuilderSession::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $other->id,
    ]);

    $this->actingAs($this->user, 'api')
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/workflow-builder/sessions/{$session->id}")
        ->assertNotFound();
});

test('unauthenticated users cannot create sessions', function () {
    $this->postJson("/api/v1/workspaces/{$this->workspace->id}/workflow-builder/sessions", [
        'title' => 'Should fail',
    ])->assertUnauthorized();
});

test('a user can sync manual canvas edits into the draft', function () {
    $session = WorkflowBuilderSession::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'nodes_draft' => [],
        'edges_draft' => [],
    ]);

    $this->actingAs($this->user, 'api')
        ->patchJson("/api/v1/workspaces/{$this->workspace->id}/workflow-builder/sessions/{$session->id}/draft", [
            'nodes' => [
                ['id' => 'node_1', 'type' => 'webhook_trigger', 'name' => 'Webhook', 'config' => [], 'position' => ['x' => 0, 'y' => 200]],
                ['id' => 'node_2', 'type' => 'slack_message', 'name' => 'Slack', 'config' => [], 'position' => ['x' => 250, 'y' => 200]],
            ],
            'edges' => [
                ['source' => 'node_1', 'target' => 'node_2'],
            ],
        ])
        ->assertOk()
        ->assertJsonCount(2, 'data.nodes_draft')
        ->assertJsonCount(1, 'data.edges_draft');

    expect($session->fresh()->nodes_draft)->toHaveCount(2)
        ->and($session->fresh()->edges_draft)->toHaveCount(1);
});

test('syncing the draft bumps the draft_lock_version and snapshots a version', function () {
    $session = WorkflowBuilderSession::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'nodes_draft' => [],
        'edges_draft' => [],
        'draft_lock_version' => 1,
    ]);

    $this->actingAs($this->user, 'api')
        ->patchJson("/api/v1/workspaces/{$this->workspace->id}/workflow-builder/sessions/{$session->id}/draft", [
            'nodes' => [
                ['id' => 'node_1', 'type' => 'webhook_trigger', 'name' => 'Webhook', 'config' => [], 'position' => ['x' => 0, 'y' => 200]],
            ],
            'edges' => [],
        ])
        ->assertOk();

    expect($session->fresh()->draft_lock_version)->toBe(2)
        ->and($session->fresh()->draftVersions()->count())->toBe(1);
});

test('another user cannot sync a draft they do not own', function () {
    $other = User::factory()->create();
    $session = WorkflowBuilderSession::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $other->id,
    ]);

    $this->actingAs($this->user, 'api')
        ->patchJson("/api/v1/workspaces/{$this->workspace->id}/workflow-builder/sessions/{$session->id}/draft", [
            'nodes' => [],
            'edges' => [],
        ])
        ->assertNotFound();
});

test('syncing a draft on a completed session is rejected', function () {
    $session = WorkflowBuilderSession::factory()->completed()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $this->actingAs($this->user, 'api')
        ->patchJson("/api/v1/workspaces/{$this->workspace->id}/workflow-builder/sessions/{$session->id}/draft", [
            'nodes' => [],
            'edges' => [],
        ])
        ->assertStatus(422);
});
