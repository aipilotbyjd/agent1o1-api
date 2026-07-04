<?php

use App\Enums\Role;
use App\Models\ActivityLog;
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

test('creating a workflow records an activity log', function () {
    $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/workflows", [
            'name' => 'Logged Flow',
        ])
        ->assertCreated();

    expect(ActivityLog::where('workspace_id', $this->workspace->id)->where('action', 'workflow.created')->exists())
        ->toBeTrue();
});

test('the activity log can be listed', function () {
    Workflow::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
    ]);

    $this->actingAs($this->user, 'api')
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/activity-logs")
        ->assertOk()
        ->assertJsonPath('data.0.action', 'workflow.created');
});
