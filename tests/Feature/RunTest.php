<?php

use App\Enums\Role;
use App\Models\Agent;
use App\Models\AgentRun;
use App\Models\Execution;
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
});

test('workflow executions and agent runs share the runs table', function () {
    $workflow = Workflow::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
    ]);
    $agent = Agent::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
    ]);

    $execution = Execution::factory()->create([
        'workflow_id' => $workflow->id,
        'workspace_id' => $this->workspace->id,
    ]);
    $agentRun = AgentRun::create([
        'agent_id' => $agent->id,
        'workspace_id' => $this->workspace->id,
        'status' => 'completed',
        'output' => 'done',
    ]);

    // Both land in the unified runs table with the right polymorphic target.
    expect(Run::count())->toBe(2);
    expect(Run::find($execution->id)->runnable_type)->toBe('workflow');
    expect(Run::find($agentRun->id)->runnable_type)->toBe('agent');

    // Per-type models stay scoped to their own kind.
    expect(Execution::count())->toBe(1);
    expect(AgentRun::count())->toBe(1);
    expect(Execution::find($agentRun->id))->toBeNull();
});

test('the unified runs endpoint lists both run types', function () {
    $workflow = Workflow::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
    ]);
    $agent = Agent::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
    ]);

    Execution::factory()->create([
        'workflow_id' => $workflow->id,
        'workspace_id' => $this->workspace->id,
    ]);
    AgentRun::create([
        'agent_id' => $agent->id,
        'workspace_id' => $this->workspace->id,
        'status' => 'completed',
    ]);

    $response = $this->actingAs($this->user, 'api')
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/runs")
        ->assertOk();

    $types = collect($response->json('data'))->pluck('runnable_type')->sort()->values()->all();
    expect($types)->toBe(['agent', 'workflow']);
});

test('the runs endpoint can filter by runnable type', function () {
    $agent = Agent::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
    ]);
    AgentRun::create([
        'agent_id' => $agent->id,
        'workspace_id' => $this->workspace->id,
        'status' => 'running',
    ]);

    $this->actingAs($this->user, 'api')
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/runs?runnable_type=agent")
        ->assertOk()
        ->assertJsonPath('data.0.runnable_type', 'agent')
        ->assertJsonCount(1, 'data');
});
