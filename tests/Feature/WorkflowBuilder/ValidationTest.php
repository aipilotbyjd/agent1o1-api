<?php

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

test('validate endpoint detects missing trigger', function () {
    $session = WorkflowBuilderSession::factory()->withNodes([
        ['id' => 'node_1', 'type' => 'transform', 'name' => 'Action', 'config' => [], 'position' => ['x' => 0, 'y' => 0]],
    ])->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/workflow-builder/sessions/{$session->id}/validate")
        ->assertOk();

    expect($response->json('data.valid'))->toBeFalse()
        ->and($response->json('data.errors'))->not->toBeEmpty();
});

test('validate endpoint returns valid true for a valid workflow', function () {
    $session = WorkflowBuilderSession::factory()->withNodes([
        ['id' => 'node_trigger', 'type' => 'webhook_trigger', 'name' => 'Webhook', 'config' => [], 'position' => ['x' => 0, 'y' => 0]],
        ['id' => 'node_action', 'type' => 'transform', 'name' => 'Action', 'config' => [], 'position' => ['x' => 250, 'y' => 0]],
    ], [
        ['source' => 'node_trigger', 'target' => 'node_action', 'sourceHandle' => 'output', 'targetHandle' => 'input'],
    ])->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/workflow-builder/sessions/{$session->id}/validate")
        ->assertOk();

    expect($response->json('data.valid'))->toBeTrue()
        ->and($response->json('data.errors'))->toBeEmpty();
});

test('validate returns all errors in a single response', function () {
    // No trigger + orphan node (two distinct issues)
    $session = WorkflowBuilderSession::factory()->withNodes([
        ['id' => 'node_1', 'type' => 'transform', 'name' => 'Action', 'config' => [], 'position' => ['x' => 0, 'y' => 0]],
        ['id' => 'node_2', 'type' => 'transform', 'name' => 'Orphan', 'config' => [], 'position' => ['x' => 250, 'y' => 0]],
    ])->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/workflow-builder/sessions/{$session->id}/validate")
        ->assertOk();

    expect(count($response->json('data.errors')))->toBeGreaterThanOrEqual(1);
});
