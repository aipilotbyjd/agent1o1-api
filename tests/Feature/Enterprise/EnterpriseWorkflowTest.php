<?php

use App\Enums\Role;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowApproval;
use App\Models\Workspace;
use App\Models\WorkspaceEnvironment;
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

test('a workflow version can be released to an environment', function () {
    $env = WorkspaceEnvironment::factory()->create(['workspace_id' => $this->workspace->id]);

    $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/workflows/{$this->workflow->id}/releases", [
            'environment_id' => $env->id,
            'version_id' => $this->workflow->current_version_id,
            'notes' => 'Ship it',
        ])
        ->assertCreated();

    $this->actingAs($this->user, 'api')
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/workflows/{$this->workflow->id}/releases")
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('an approval can be requested and approved', function () {
    $approval = $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/workflows/{$this->workflow->id}/approvals", [
            'notes' => 'Please review',
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->json('data.id');

    $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/workflows/{$this->workflow->id}/approvals/{$approval}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    expect(WorkflowApproval::find($approval)->reviewed_by)->toBe($this->user->id);
});

test('a contract is generated and passes against the unchanged workflow', function () {
    $contract = $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/workflows/{$this->workflow->id}/contracts")
        ->assertCreated()
        ->json('data.id');

    $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/workflows/{$this->workflow->id}/contracts/{$contract}/run")
        ->assertOk()
        ->assertJsonPath('data.status', 'passed');
});
