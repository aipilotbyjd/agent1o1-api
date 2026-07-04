<?php

use App\Enums\Role;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowTemplate;
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

test('the public catalog lists active workflow templates', function () {
    WorkflowTemplate::factory()->count(2)->create();
    WorkflowTemplate::factory()->inactive()->create();

    $this->getJson('/api/v1/workflow-templates')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('deploying a template creates a workflow with the template graph', function () {
    $template = WorkflowTemplate::factory()->create();

    $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/workflow-templates/{$template->id}/deploy")
        ->assertCreated();

    $workflow = Workflow::where('workspace_id', $this->workspace->id)->first();
    expect($workflow)->not->toBeNull()
        ->and($workflow->currentVersion->nodes_data)->toHaveCount(2)
        ->and($template->fresh()->usage_count)->toBe(1);
});

test('a non-admin cannot create workflow templates', function () {
    $this->actingAs($this->user, 'api')
        ->postJson('/api/v1/workflow-templates', [
            'name' => 'Template',
            'category' => 'ops',
            'nodes' => [],
        ])
        ->assertForbidden();
});

test('an admin can create a workflow template', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin, 'api')
        ->postJson('/api/v1/workflow-templates', [
            'name' => 'Admin Template',
            'category' => 'ops',
            'nodes' => [['id' => 'trigger-1', 'type' => 'trigger', 'name' => 'Start']],
        ])
        ->assertCreated()
        ->assertJsonPath('data.slug', 'admin-template');
});
