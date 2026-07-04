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

test('a workflow can be exported then re-imported', function () {
    $exported = $this->actingAs($this->user, 'api')
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/workflows/{$this->workflow->id}/export")
        ->assertOk()
        ->json('data');

    expect($exported['nodes'])->toHaveCount(2);

    $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/workflows/import", [
            'name' => $exported['name'],
            'nodes' => $exported['nodes'],
            'edges' => $exported['edges'],
        ])
        ->assertCreated();

    expect(Workflow::where('workspace_id', $this->workspace->id)->count())->toBe(2);
});
