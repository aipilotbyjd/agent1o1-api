<?php

use App\Enums\Role;
use App\Models\User;
use App\Models\WorkflowBuilderDraftVersion;
use App\Models\WorkflowBuilderSession;
use App\Models\Workspace;
use App\Services\WorkflowBuilder\DraftService;
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

test('adding a node creates a draft version snapshot', function () {
    $session = WorkflowBuilderSession::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    app(DraftService::class)->addNode($session, [
        'id' => 'node_1',
        'type' => 'trigger',
        'name' => 'Webhook',
        'config' => [],
        'position' => ['x' => 0, 'y' => 200],
    ]);

    expect(WorkflowBuilderDraftVersion::where('session_id', $session->id)->count())->toBe(1);
});

test('each mutation increments draft_lock_version', function () {
    $session = WorkflowBuilderSession::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    expect($session->draft_lock_version)->toBe(0);

    app(DraftService::class)->addNode($session, [
        'id' => 'node_1', 'type' => 'trigger', 'name' => 'Webhook', 'config' => [], 'position' => ['x' => 0, 'y' => 0],
    ]);

    expect($session->fresh()->draft_lock_version)->toBe(1);
});

test('a user can list draft versions', function () {
    $session = WorkflowBuilderSession::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    WorkflowBuilderDraftVersion::factory()->create([
        'session_id' => $session->id,
        'label' => 'Version 1',
    ]);

    $this->actingAs($this->user, 'api')
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/workflow-builder/sessions/{$session->id}/versions")
        ->assertOk()
        ->assertJsonPath('pagination.total', 1);
});

test('restoring a version resets the draft', function () {
    $session = WorkflowBuilderSession::factory()->withNodes([
        ['id' => 'node_a', 'type' => 'trigger', 'name' => 'Start', 'config' => [], 'position' => ['x' => 0, 'y' => 0]],
    ])->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $version = WorkflowBuilderDraftVersion::factory()->create([
        'session_id' => $session->id,
        'nodes_snapshot' => [['id' => 'node_a', 'type' => 'trigger', 'name' => 'Start', 'config' => [], 'position' => ['x' => 0, 'y' => 0]]],
        'edges_snapshot' => [],
        'label' => 'Before adding slack',
    ]);

    // Add another node
    app(DraftService::class)->addNode($session->fresh(), [
        'id' => 'node_b', 'type' => 'slack', 'name' => 'Slack', 'config' => [], 'position' => ['x' => 250, 'y' => 0],
    ]);

    expect($session->fresh()->nodes_draft)->toHaveCount(2);

    // Restore
    $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/workflow-builder/sessions/{$session->id}/versions/{$version->id}/restore")
        ->assertOk();

    expect($session->fresh()->nodes_draft)->toHaveCount(1);
});

test('restoring a version creates a new snapshot labelled Restored from', function () {
    $session = WorkflowBuilderSession::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $version = WorkflowBuilderDraftVersion::factory()->create([
        'session_id' => $session->id,
        'nodes_snapshot' => [],
        'edges_snapshot' => [],
    ]);

    $countBefore = WorkflowBuilderDraftVersion::count();

    $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/workflow-builder/sessions/{$session->id}/versions/{$version->id}/restore")
        ->assertOk();

    expect(WorkflowBuilderDraftVersion::count())->toBe($countBefore + 1);

    $newest = WorkflowBuilderDraftVersion::where('session_id', $session->id)->latest()->first();
    expect($newest->label)->toStartWith('Restored from');
});
