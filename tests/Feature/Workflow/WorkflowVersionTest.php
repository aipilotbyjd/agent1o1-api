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

    $this->workflow = Workflow::factory()->withSimpleGraph()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
    ]);
});

test('versions are listed for a workflow', function () {
    $this->actingAs($this->user, 'api')
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/workflows/{$this->workflow->id}/versions")
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('a version can be published and becomes current', function () {
    $version = $this->workflow->currentVersion;

    $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/workflows/{$this->workflow->id}/versions/{$version->id}/publish")
        ->assertOk()
        ->assertJsonPath('data.is_published', true);

    expect($this->workflow->fresh()->current_version_id)->toBe($version->id)
        ->and($version->fresh()->is_published)->toBeTrue();
});

test('rollback clones an old version as the new latest', function () {
    $original = $this->workflow->currentVersion;

    $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/workflows/{$this->workflow->id}/versions/{$original->id}/rollback")
        ->assertCreated()
        ->assertJsonPath('data.version_number', 2);

    expect($this->workflow->fresh()->versions)->toHaveCount(2);
});

test('diff reports added and removed nodes between versions', function () {
    $v1 = $this->workflow->currentVersion;
    $v2 = $this->workflow->versions()->create([
        'workspace_id' => $this->workspace->id,
        'version_number' => 2,
        'nodes_data' => [
            ['id' => 'trigger-1', 'type' => 'trigger', 'name' => 'Start'],
            ['id' => 'new-node', 'type' => 'transform', 'name' => 'New'],
        ],
        'edges_data' => [],
    ]);

    $this->actingAs($this->user, 'api')
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/workflows/{$this->workflow->id}/versions/diff/{$v1->id}/{$v2->id}")
        ->assertOk()
        ->assertJsonPath('data.added', ['new-node'])
        ->assertJsonPath('data.removed', ['transform-1']);
});
