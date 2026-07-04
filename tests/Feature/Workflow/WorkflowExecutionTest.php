<?php

use App\Engine\WorkflowRunner;
use App\Enums\ExecutionStatus;
use App\Enums\Role;
use App\Events\ExecutionCompletedEvent;
use App\Events\ExecutionStartedEvent;
use App\Jobs\ExecuteWorkflowJob;
use App\Models\Execution;
use App\Models\User;
use App\Models\Workflow;
use App\Models\Workspace;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\Event;
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

    $this->workflow = Workflow::factory()->active()->withSimpleGraph()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
    ]);
});

test('executing a workflow queues an engine job and returns the reverb channel', function () {
    Queue::fake();

    $response = $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/workflows/{$this->workflow->id}/execute", [
            'trigger_data' => ['source' => 'test'],
        ]);

    $response->assertStatus(202)
        ->assertJsonPath('data.execution.status', 'pending');

    $execution = Execution::first();
    expect($execution)->not->toBeNull()
        ->and($execution->trigger_data)->toBe(['source' => 'test'])
        ->and($response->json('data.channel'))->toBe("private-execution.{$execution->id}");

    Queue::assertPushedOn('engine', ExecuteWorkflowJob::class);
});

test('the runner executes a simple graph to completion', function () {
    Event::fake(); // capture broadcasts without a running Reverb server

    $execution = Execution::factory()->create([
        'workflow_id' => $this->workflow->id,
        'workspace_id' => $this->workspace->id,
        'trigger_data' => ['hello' => 'world'],
    ]);

    app(WorkflowRunner::class)->run($execution);

    $execution->refresh();

    expect($execution->status)->toBe(ExecutionStatus::Completed)
        ->and($execution->nodes()->count())->toBe(2)
        ->and($execution->nodes()->where('node_id', 'transform-1')->first()->output_data)
        ->toBe(['greeting' => 'hello']);

    Event::assertDispatched(ExecutionStartedEvent::class);
    Event::assertDispatched(ExecutionCompletedEvent::class);
});

test('failed executions can be retried', function () {
    Queue::fake();

    $failed = Execution::factory()->failed()->create([
        'workflow_id' => $this->workflow->id,
        'workspace_id' => $this->workspace->id,
    ]);

    $response = $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/executions/{$failed->id}/retry");

    $response->assertStatus(202);

    expect(Execution::where('parent_execution_id', $failed->id)->exists())->toBeTrue();
});

test('completed executions cannot be retried', function () {
    $completed = Execution::factory()->completed()->create([
        'workflow_id' => $this->workflow->id,
        'workspace_id' => $this->workspace->id,
    ]);

    $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/executions/{$completed->id}/retry")
        ->assertStatus(422);
});

test('a running execution can be cancelled', function () {
    $running = Execution::factory()->running()->create([
        'workflow_id' => $this->workflow->id,
        'workspace_id' => $this->workspace->id,
    ]);

    $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/executions/{$running->id}/cancel")
        ->assertOk();

    expect($running->fresh()->status)->toBe(ExecutionStatus::Cancelled);
});

test('executions are filterable by status', function () {
    Execution::factory()->completed()->count(2)->create([
        'workflow_id' => $this->workflow->id,
        'workspace_id' => $this->workspace->id,
    ]);
    Execution::factory()->failed()->create([
        'workflow_id' => $this->workflow->id,
        'workspace_id' => $this->workspace->id,
    ]);

    $response = $this->actingAs($this->user, 'api')
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/executions?status=failed");

    $response->assertOk()->assertJsonCount(1, 'data');
});
