<?php

use App\Engine\NodeResult;
use App\Enums\Role;
use App\Events\ExecutionStartedEvent;
use App\Events\NodeCompletedEvent;
use App\Models\ExecutionLog;
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

    $this->execution = Run::factory()->create([
        'workflow_id' => $this->workflow->id,
        'workspace_id' => $this->workspace->id,
    ]);
});

test('execution events are recorded as logs', function () {
    event(new ExecutionStartedEvent($this->execution));

    expect($this->execution->logs()->count())->toBe(1)
        ->and($this->execution->logs()->first()->message)->toBe('Execution started.');
});

test('sensitive node output is masked in logs', function () {
    event(new NodeCompletedEvent(
        $this->execution,
        'node-1',
        NodeResult::completed(['api_key' => 'super-secret', 'name' => 'Ada']),
        1,
    ));

    $log = $this->execution->logs()->first();

    expect($log->context['output']['api_key'])->toBe('***redacted***')
        ->and($log->context['output']['name'])->toBe('Ada');
});

test('logs can be listed via the api', function () {
    ExecutionLog::create([
        'execution_id' => $this->execution->id,
        'workspace_id' => $this->workspace->id,
        'level' => 'info',
        'message' => 'Hello',
        'logged_at' => now(),
    ]);

    $this->actingAs($this->user, 'api')
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/executions/{$this->execution->id}/logs")
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
