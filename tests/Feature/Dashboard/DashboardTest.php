<?php

use App\Enums\Role;
use App\Models\Execution;
use App\Models\UsageDailySnapshot;
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
        'is_active' => true,
    ]);
});

test('overview returns workflow and execution KPIs', function () {
    Execution::factory()->completed()->count(3)->create([
        'workflow_id' => $this->workflow->id,
        'workspace_id' => $this->workspace->id,
        'credits_consumed' => 2,
    ]);
    Execution::factory()->failed()->create([
        'workflow_id' => $this->workflow->id,
        'workspace_id' => $this->workspace->id,
        'credits_consumed' => 1,
    ]);

    $this->actingAs($this->user, 'api')
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/dashboard/overview")
        ->assertOk()
        ->assertJsonPath('data.workflows.total', 1)
        ->assertJsonPath('data.workflows.active', 1)
        ->assertJsonPath('data.executions.in_range', 4)
        ->assertJsonPath('data.executions.succeeded', 3)
        ->assertJsonPath('data.executions.failed', 1)
        ->assertJsonPath('data.executions.success_rate', 75)
        ->assertJsonPath('data.credits.consumed_in_range', 7);
});

test('trends returns a continuous daily series from snapshots', function () {
    UsageDailySnapshot::create([
        'workspace_id' => $this->workspace->id,
        'snapshot_date' => now()->subDay()->toDateString(),
        'credits_used' => 10,
        'executions_total' => 5,
        'executions_succeeded' => 4,
        'executions_failed' => 1,
        'nodes_executed' => 20,
        'ai_nodes_executed' => 3,
    ]);

    $response = $this->actingAs($this->user, 'api')
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/dashboard/trends?days=3")
        ->assertOk();

    // 3-day window yields exactly 3 daily buckets, gaps zero-filled.
    expect($response->json('data.series'))->toHaveCount(3);

    $yesterday = collect($response->json('data.series'))
        ->firstWhere('date', now()->subDay()->toDateString());

    expect($yesterday['executions_total'])->toBe(5)
        ->and($yesterday['credits_used'])->toBe(10);
});

test('top workflows are ranked by execution volume', function () {
    $busy = Workflow::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
        'name' => 'Busy workflow',
    ]);

    Execution::factory()->completed()->count(5)->create([
        'workflow_id' => $busy->id,
        'workspace_id' => $this->workspace->id,
    ]);
    Execution::factory()->completed()->create([
        'workflow_id' => $this->workflow->id,
        'workspace_id' => $this->workspace->id,
    ]);

    $this->actingAs($this->user, 'api')
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/dashboard/top-workflows")
        ->assertOk()
        ->assertJsonPath('data.0.workflow_id', $busy->id)
        ->assertJsonPath('data.0.executions', 5)
        ->assertJsonPath('data.0.success_rate', 100);
});

test('recent activity returns the latest executions', function () {
    Execution::factory()->completed()->count(3)->create([
        'workflow_id' => $this->workflow->id,
        'workspace_id' => $this->workspace->id,
    ]);

    $this->actingAs($this->user, 'api')
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/dashboard/recent-activity?limit=2")
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('dashboard endpoints require authentication', function () {
    $this->getJson("/api/v1/workspaces/{$this->workspace->id}/dashboard/overview")
        ->assertUnauthorized();
});
