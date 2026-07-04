<?php

use App\Enums\Role;
use App\Jobs\DiagnoseFailedNode;
use App\Models\AiFixSuggestion;
use App\Models\Execution;
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

    $this->execution = Execution::factory()->failed()->create([
        'workflow_id' => $this->workflow->id,
        'workspace_id' => $this->workspace->id,
    ]);
});

test('diagnosing a failed node queues the diagnosis job', function () {
    Queue::fake();

    $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/executions/{$this->execution->id}/autofix", [
            'node_id' => 'transform-1',
        ])
        ->assertStatus(202);

    Queue::assertPushed(DiagnoseFailedNode::class, fn ($job) => $job->executionId === $this->execution->id
        && $job->nodeId === 'transform-1');
});

test('fix suggestions are listed for an execution', function () {
    AiFixSuggestion::factory()->create([
        'execution_id' => $this->execution->id,
        'workspace_id' => $this->workspace->id,
    ]);

    $this->actingAs($this->user, 'api')
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/executions/{$this->execution->id}/autofix")
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('applying a suggestion patches the workflow version node config', function () {
    $suggestion = AiFixSuggestion::factory()->create([
        'execution_id' => $this->execution->id,
        'workspace_id' => $this->workspace->id,
        'node_id' => 'transform-1',
        'suggestions' => [
            ['title' => 'Set output', 'description' => 'fix', 'fix_config' => ['output' => ['fixed' => true]]],
        ],
    ]);

    $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/executions/{$this->execution->id}/autofix/{$suggestion->id}/apply", [
            'suggestion_index' => 0,
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'applied');

    $node = collect($this->workflow->fresh()->currentVersion->nodes_data)->firstWhere('id', 'transform-1');
    expect($node['config']['output']['fixed'])->toBeTrue();
});
