<?php

use App\Enums\ExecutionStatus;
use App\Enums\Role;
use App\Models\Agent;
use App\Models\Run;
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
    $this->agent = Agent::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
    ]);
});

function workflowRun(Workspace $ws, Workflow $wf, array $attrs = []): Run
{
    return Run::factory()->create(array_merge([
        'workflow_id' => $wf->id,
        'workspace_id' => $ws->id,
    ], $attrs));
}

function agentRun(Workspace $ws, Agent $agent, array $attrs = []): Run
{
    return Run::create(array_merge([
        'agent_id' => $agent->id,
        'workspace_id' => $ws->id,
        'status' => ExecutionStatus::Running,
    ], $attrs));
}

test('runs index returns both workflow and agent runs', function () {
    workflowRun($this->workspace, $this->workflow);
    agentRun($this->workspace, $this->agent);

    $this->actingAs($this->user, 'api')
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/runs")
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('a workflow run can be shown, and nodes are workflow-only', function () {
    $run = workflowRun($this->workspace, $this->workflow);

    $this->actingAs($this->user, 'api')
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/runs/{$run->id}")
        ->assertOk()
        ->assertJsonPath('data.runnable_type', 'workflow');

    $this->actingAs($this->user, 'api')
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/runs/{$run->id}/nodes")
        ->assertOk();

    // steps are agent-only
    $this->actingAs($this->user, 'api')
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/runs/{$run->id}/steps")
        ->assertStatus(422);
});

test('an agent run exposes steps and can be cancelled', function () {
    $run = agentRun($this->workspace, $this->agent);

    $this->actingAs($this->user, 'api')
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/runs/{$run->id}/steps")
        ->assertOk();

    // nodes are workflow-only
    $this->actingAs($this->user, 'api')
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/runs/{$run->id}/nodes")
        ->assertStatus(422);

    // agent runs gain cancel through the unified surface
    $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/runs/{$run->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', ExecutionStatus::Cancelled->value);
});

test('retry is rejected for agent runs', function () {
    $run = agentRun($this->workspace, $this->agent, ['status' => ExecutionStatus::Failed]);

    $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/runs/{$run->id}/retry")
        ->assertStatus(422);
});

test('a terminal run can be deleted', function () {
    $run = workflowRun($this->workspace, $this->workflow, ['status' => ExecutionStatus::Completed]);

    $this->actingAs($this->user, 'api')
        ->deleteJson("/api/v1/workspaces/{$this->workspace->id}/runs/{$run->id}")
        ->assertOk();

    expect(Run::find($run->id))->toBeNull();
});

test('a run from another workspace is not found', function () {
    $other = Workspace::factory()->create(['owner_id' => $this->user->id]);
    $run = agentRun($other, Agent::factory()->create(['workspace_id' => $other->id, 'created_by' => $this->user->id]));

    $this->actingAs($this->user, 'api')
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/runs/{$run->id}")
        ->assertNotFound();
});
