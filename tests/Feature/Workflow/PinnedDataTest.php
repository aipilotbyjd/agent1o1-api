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

    $this->workflow = Workflow::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
    ]);
});

test('pinning node data is idempotent per node', function () {
    $url = "/api/v1/workspaces/{$this->workspace->id}/workflows/{$this->workflow->id}/pinned-data";

    $this->actingAs($this->user, 'api')
        ->postJson($url, ['node_id' => 'node-1', 'data' => ['value' => 1]])
        ->assertCreated();

    $this->actingAs($this->user, 'api')
        ->postJson($url, ['node_id' => 'node-1', 'data' => ['value' => 2]])
        ->assertCreated();

    $this->actingAs($this->user, 'api')
        ->getJson($url)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.data.value', 2);
});

test('pinned data can be removed by node id', function () {
    $url = "/api/v1/workspaces/{$this->workspace->id}/workflows/{$this->workflow->id}/pinned-data";

    $this->actingAs($this->user, 'api')
        ->postJson($url, ['node_id' => 'node-1', 'data' => ['value' => 1]])
        ->assertCreated();

    $this->actingAs($this->user, 'api')
        ->deleteJson("{$url}/node-1")
        ->assertOk();

    $this->actingAs($this->user, 'api')
        ->getJson($url)
        ->assertOk()
        ->assertJsonCount(0, 'data');
});
