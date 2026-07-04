<?php

use App\Enums\Role;
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
});

test('member can create a workflow with nodes and edges', function () {
    $response = $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/workflows", [
            'name' => 'My Flow',
            'description' => 'Test workflow',
            'nodes' => [
                ['id' => 'trigger-1', 'type' => 'trigger', 'name' => 'Start'],
                ['id' => 'transform-1', 'type' => 'transform', 'name' => 'Out', 'config' => ['output' => ['ok' => true]]],
            ],
            'edges' => [
                ['source' => 'trigger-1', 'target' => 'transform-1'],
            ],
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'My Flow');

    $workflow = Workflow::where('name', 'My Flow')->first();
    expect($workflow)->not->toBeNull()
        ->and($workflow->currentVersion->nodes_data)->toHaveCount(2)
        ->and($workflow->currentVersion->edges_data)->toHaveCount(1);
});

test('workflows are listed for the workspace', function () {
    Workflow::factory()->count(3)->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user, 'api')
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/workflows");

    $response->assertOk()->assertJsonCount(3, 'data');
});

test('updating nodes creates a new version', function () {
    $workflow = Workflow::factory()->withSimpleGraph()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
    ]);

    $this->actingAs($this->user, 'api')
        ->putJson("/api/v1/workspaces/{$this->workspace->id}/workflows/{$workflow->id}", [
            'nodes' => [['id' => 'trigger-1', 'type' => 'trigger', 'name' => 'New Start']],
            'edges' => [],
        ])
        ->assertOk();

    expect($workflow->fresh()->versions)->toHaveCount(2)
        ->and($workflow->fresh()->currentVersion->version_number)->toBe(2);
});

test('locked workflow rejects updates', function () {
    $workflow = Workflow::factory()->locked()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
    ]);

    $this->actingAs($this->user, 'api')
        ->putJson("/api/v1/workspaces/{$this->workspace->id}/workflows/{$workflow->id}", ['name' => 'Nope'])
        ->assertStatus(423);
});

test('workflow with a valid graph can be activated', function () {
    $workflow = Workflow::factory()->withSimpleGraph()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
    ]);

    $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/workflows/{$workflow->id}/activate")
        ->assertOk();

    expect($workflow->fresh()->is_active)->toBeTrue();
});

test('workflow without nodes cannot be activated', function () {
    $workflow = Workflow::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
    ]);

    $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/workflows/{$workflow->id}/activate")
        ->assertStatus(422);
});

test('duplicate copies the graph and resets state', function () {
    $workflow = Workflow::factory()->active()->withSimpleGraph()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
    ]);

    $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/workflows/{$workflow->id}/duplicate")
        ->assertCreated();

    $copy = Workflow::where('name', "{$workflow->name} (copy)")->first();
    expect($copy)->not->toBeNull()
        ->and($copy->is_active)->toBeFalse()
        ->and($copy->currentVersion->nodes_data)->toEqual($workflow->currentVersion->nodes_data);
});

test('non-member cannot access workflows', function () {
    $outsider = User::factory()->create();

    $this->actingAs($outsider, 'api')
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/workflows")
        ->assertForbidden();
});

test('workflow can be deleted', function () {
    $workflow = Workflow::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
    ]);

    $this->actingAs($this->user, 'api')
        ->deleteJson("/api/v1/workspaces/{$this->workspace->id}/workflows/{$workflow->id}")
        ->assertOk();

    expect(Workflow::find($workflow->id))->toBeNull();
});
