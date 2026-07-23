<?php

use App\Enums\ExecutionNodeStatus;
use App\Enums\ExecutionStatus;
use App\Enums\Role;
use App\Models\ExecutionNode;
use App\Models\Node;
use App\Models\Run;
use App\Models\User;
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

test('recently used returns default nodes when workspace has no executions', function () {
    Node::factory()->count(3)->create(['is_active' => true]);

    $response = $this->actingAs($this->user, 'api')
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/nodes/recently-used")
        ->assertOk();

    expect($response->json('data.is_default'))->toBeTrue();
    expect($response->json('data.nodes'))->not->toBeEmpty();
});

test('recently used returns nodes sorted by usage frequency', function () {
    $nodeA = Node::factory()->create(['is_active' => true, 'type' => 'transform']);
    $nodeB = Node::factory()->create(['is_active' => true, 'type' => 'http_request']);

    $execution = Run::factory()->create([
        'workspace_id' => $this->workspace->id,
        'status' => ExecutionStatus::Completed,
    ]);

    ExecutionNode::factory()->count(3)->create([
        'execution_id' => $execution->id,
        'node_type' => $nodeA->type,
        'status' => ExecutionNodeStatus::Completed,
    ]);

    ExecutionNode::factory()->create([
        'execution_id' => $execution->id,
        'node_type' => $nodeB->type,
        'status' => ExecutionNodeStatus::Completed,
    ]);

    $response = $this->actingAs($this->user, 'api')
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/nodes/recently-used")
        ->assertOk();

    expect($response->json('data.is_default'))->toBeFalse();
    expect($response->json('data.nodes.0.type'))->toBe($nodeA->type);
});

test('custom nodes returns only custom nodes for the workspace', function () {
    Node::factory()->create(['is_active' => true, 'is_custom' => true, 'workspace_id' => $this->workspace->id]);
    Node::factory()->create(['is_active' => true, 'is_custom' => false, 'workspace_id' => $this->workspace->id]);

    $response = $this->actingAs($this->user, 'api')
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/nodes/custom")
        ->assertOk();

    expect($response->json('data.total'))->toBe(1);
    expect($response->json('data.nodes'))->toHaveCount(1);
});

test('custom nodes excludes custom nodes from other workspaces', function () {
    $otherWorkspace = Workspace::factory()->create();
    Node::factory()->create(['is_active' => true, 'is_custom' => true, 'workspace_id' => $otherWorkspace->id]);

    $response = $this->actingAs($this->user, 'api')
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/nodes/custom")
        ->assertOk();

    expect($response->json('data.total'))->toBe(0);
});

test('node library endpoints require workspace membership', function () {
    $stranger = User::factory()->create();
    $otherWorkspace = Workspace::factory()->create();

    $this->actingAs($stranger, 'api')
        ->getJson("/api/v1/workspaces/{$otherWorkspace->id}/nodes/recently-used")
        ->assertForbidden();

    $this->actingAs($stranger, 'api')
        ->getJson("/api/v1/workspaces/{$otherWorkspace->id}/nodes/custom")
        ->assertForbidden();
});
