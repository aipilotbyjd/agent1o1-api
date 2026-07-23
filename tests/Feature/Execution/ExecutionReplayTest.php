<?php

use App\Enums\Role;
use App\Jobs\ExecuteWorkflowJob;
use App\Models\ExecutionReplayPack;
use App\Models\Run;
use App\Models\User;
use App\Models\Workflow;
use App\Models\Workspace;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\Queue;
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

    $this->execution = Run::factory()->completed()->create([
        'workflow_id' => $this->workflow->id,
        'workspace_id' => $this->workspace->id,
        'trigger_data' => ['foo' => 'bar'],
    ]);
});

test('a replay pack captures the workflow snapshot and trigger data', function () {
    $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/runs/{$this->execution->id}/replay-pack", [
            'label' => 'Repro #1',
        ])
        ->assertCreated()
        ->assertJsonPath('data.label', 'Repro #1')
        ->assertJsonPath('data.trigger_data.foo', 'bar');

    expect(ExecutionReplayPack::count())->toBe(1);
});

test('replaying a pack queues a new execution', function () {
    Queue::fake();

    $pack = ExecutionReplayPack::create([
        'workspace_id' => $this->workspace->id,
        'workflow_id' => $this->workflow->id,
        'execution_id' => $this->execution->id,
        'created_by' => $this->user->id,
        'version_snapshot' => ['nodes' => [], 'edges' => []],
        'trigger_data' => ['foo' => 'bar'],
    ]);

    $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/replay-packs/{$pack->id}/replay")
        ->assertStatus(202);

    Queue::assertPushed(ExecuteWorkflowJob::class);
});
