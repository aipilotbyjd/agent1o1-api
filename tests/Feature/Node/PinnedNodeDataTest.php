<?php

use App\Enums\Role;
use App\Models\PinnedNodeData;
use App\Models\User;
use App\Models\Workflow;
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
    $this->workflow = Workflow::factory()->create(['workspace_id' => $this->workspace->id]);
});

test('can list pinned data for a workflow', function () {
    PinnedNodeData::factory()->count(2)->create([
        'workflow_id' => $this->workflow->id,
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
    ]);

    $this->actingAs($this->user, 'api')
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/workflows/{$this->workflow->id}/pinned-data")
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('can pin node data to a workflow', function () {
    $nodeId = Str::uuid()->toString();

    $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/workflows/{$this->workflow->id}/pinned-data", [
            'node_id' => $nodeId,
            'data' => ['foo' => 'bar'],
        ])
        ->assertCreated()
        ->assertJsonPath('data.node_id', $nodeId);

    $this->assertDatabaseHas('pinned_node_data', [
        'workflow_id' => $this->workflow->id,
        'node_id' => $nodeId,
    ]);
});

test('pinning the same node id updates existing data', function () {
    $nodeId = Str::uuid()->toString();

    PinnedNodeData::factory()->create([
        'workflow_id' => $this->workflow->id,
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
        'node_id' => $nodeId,
        'data' => ['old' => true],
    ]);

    $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/workflows/{$this->workflow->id}/pinned-data", [
            'node_id' => $nodeId,
            'data' => ['new' => true],
        ])
        ->assertCreated();

    $this->assertDatabaseCount('pinned_node_data', 1);
    $this->assertDatabaseHas('pinned_node_data', ['node_id' => $nodeId]);
});

test('can delete pinned data by node id', function () {
    $nodeId = Str::uuid()->toString();

    PinnedNodeData::factory()->create([
        'workflow_id' => $this->workflow->id,
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
        'node_id' => $nodeId,
    ]);

    $this->actingAs($this->user, 'api')
        ->deleteJson("/api/v1/workspaces/{$this->workspace->id}/workflows/{$this->workflow->id}/pinned-data/{$nodeId}")
        ->assertOk();

    $this->assertDatabaseMissing('pinned_node_data', ['node_id' => $nodeId]);
});

test('requires workflow update permission to pin data', function () {
    $viewer = User::factory()->create();
    $this->workspace->members()->attach($viewer->id, [
        'id' => Str::uuid()->toString(),
        'role' => Role::Viewer,
        'joined_at' => now(),
    ]);

    $this->actingAs($viewer, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/workflows/{$this->workflow->id}/pinned-data", [
            'node_id' => Str::uuid()->toString(),
            'data' => ['x' => 1],
        ])
        ->assertForbidden();
});
