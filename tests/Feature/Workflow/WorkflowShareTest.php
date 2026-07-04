<?php

use App\Enums\Role;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowShare;
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

test('a share link can be created for a workflow', function () {
    $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/workflows/{$this->workflow->id}/shares", [
            'allow_clone' => true,
        ])
        ->assertCreated()
        ->assertJsonStructure(['data' => ['token', 'url']]);
});

test('a shared workflow can be viewed publicly', function () {
    $share = WorkflowShare::factory()->create([
        'workflow_id' => $this->workflow->id,
        'workspace_id' => $this->workspace->id,
    ]);

    $this->getJson("/api/v1/shared/{$share->token}")
        ->assertOk()
        ->assertJsonPath('data.name', $this->workflow->name)
        ->assertJsonCount(2, 'data.nodes');

    expect($share->fresh()->view_count)->toBe(1);
});

test('a shared workflow can be cloned into the current workspace', function () {
    $this->user->update(['current_workspace_id' => $this->workspace->id]);

    $share = WorkflowShare::factory()->create([
        'workflow_id' => $this->workflow->id,
        'workspace_id' => $this->workspace->id,
        'allow_clone' => true,
    ]);

    $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/shared/{$share->token}/clone")
        ->assertCreated();

    expect(Workflow::where('workspace_id', $this->workspace->id)->count())->toBe(2);
});

test('expired shares are not viewable', function () {
    $share = WorkflowShare::factory()->create([
        'workflow_id' => $this->workflow->id,
        'workspace_id' => $this->workspace->id,
        'expires_at' => now()->subDay(),
    ]);

    $this->getJson("/api/v1/shared/{$share->token}")->assertNotFound();
});
