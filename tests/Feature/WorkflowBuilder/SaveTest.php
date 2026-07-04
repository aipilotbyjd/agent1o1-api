<?php

use App\Enums\BuilderSessionStatus;
use App\Enums\Role;
use App\Models\User;
use App\Models\Workflow;
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

test('saving a valid session creates a workflow and marks session completed', function () {
    $session = WorkflowBuilderSession::factory()->withNodes([
        ['id' => 'node_trigger', 'type' => 'webhook_trigger', 'name' => 'Webhook', 'config' => [], 'position' => ['x' => 0, 'y' => 0]],
        ['id' => 'node_action', 'type' => 'transform', 'name' => 'Action', 'config' => [], 'position' => ['x' => 250, 'y' => 0]],
    ], [
        ['source' => 'node_trigger', 'target' => 'node_action', 'sourceHandle' => 'output', 'targetHandle' => 'input'],
    ])->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'title' => 'My Workflow',
    ]);

    $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/workflow-builder/sessions/{$session->id}/save")
        ->assertCreated()
        ->assertJsonPath('data.name', 'My Workflow');

    expect($session->fresh()->status)->toBe(BuilderSessionStatus::Completed)
        ->and($session->fresh()->workflow_id)->not->toBeNull();
});

test('saving a session with validation errors returns 422', function () {
    $session = WorkflowBuilderSession::factory()->withNodes([
        ['id' => 'node_1', 'type' => 'transform', 'name' => 'Action', 'config' => [], 'position' => ['x' => 0, 'y' => 0]],
    ])->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/workflow-builder/sessions/{$session->id}/save")
        ->assertStatus(422);

    expect($session->fresh()->status)->toBe(BuilderSessionStatus::Active);
});

test('saving creates a new workflow version when session is linked to an existing workflow', function () {
    $existing = Workflow::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
        'name' => 'Original',
    ]);

    $session = WorkflowBuilderSession::factory()->withNodes([
        ['id' => 'node_trigger', 'type' => 'webhook_trigger', 'name' => 'Webhook', 'config' => [], 'position' => ['x' => 0, 'y' => 0]],
    ])->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'workflow_id' => $existing->id,
        'title' => 'Updated',
    ]);

    $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/workflow-builder/sessions/{$session->id}/save")
        ->assertCreated();

    expect($session->fresh()->workflow_id)->toBe($existing->id);
});
