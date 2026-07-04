<?php

use App\Enums\Role;
use App\Jobs\RunAgentJob;
use App\Models\Agent;
use App\Models\AgentTrigger;
use App\Models\User;
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

    $this->agent = Agent::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
    ]);
});

test('a trigger can be created for an agent', function () {
    $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/agents/{$this->agent->id}/triggers", [
            'type' => 'webhook',
            'initial_message' => 'Run please.',
        ])
        ->assertCreated()
        ->assertJsonPath('data.type', 'webhook');

    expect($this->agent->triggers()->count())->toBe(1);
});

test('firing a trigger dispatches the agent job', function () {
    Queue::fake();

    $trigger = AgentTrigger::factory()->create([
        'agent_id' => $this->agent->id,
        'workspace_id' => $this->workspace->id,
    ]);

    $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/agents/{$this->agent->id}/triggers/{$trigger->id}/fire")
        ->assertStatus(202);

    Queue::assertPushed(RunAgentJob::class, fn (RunAgentJob $job) => $job->agentId === $this->agent->id
        && $job->triggerId === $trigger->id);
});

test('a public agent webhook dispatches the agent job', function () {
    Queue::fake();

    $trigger = AgentTrigger::factory()->webhook()->create([
        'agent_id' => $this->agent->id,
        'workspace_id' => $this->workspace->id,
        'initial_message' => null,
    ]);

    $this->postJson("/api/v1/agent-webhooks/{$trigger->id}", ['message' => 'Hello from outside'])
        ->assertStatus(202);

    Queue::assertPushed(RunAgentJob::class, fn (RunAgentJob $job) => $job->agentId === $this->agent->id
        && $job->message === 'Hello from outside');
});

test('a webhook for an inactive agent is rejected', function () {
    Queue::fake();

    $agent = Agent::factory()->inactive()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
    ]);
    $trigger = AgentTrigger::factory()->webhook()->create([
        'agent_id' => $agent->id,
        'workspace_id' => $this->workspace->id,
    ]);

    $this->postJson("/api/v1/agent-webhooks/{$trigger->id}", ['message' => 'hi'])
        ->assertStatus(422);

    Queue::assertNotPushed(RunAgentJob::class);
});

test('a trigger can be deleted', function () {
    $trigger = AgentTrigger::factory()->create([
        'agent_id' => $this->agent->id,
        'workspace_id' => $this->workspace->id,
    ]);

    $this->actingAs($this->user, 'api')
        ->deleteJson("/api/v1/workspaces/{$this->workspace->id}/agents/{$this->agent->id}/triggers/{$trigger->id}")
        ->assertOk();

    expect(AgentTrigger::find($trigger->id))->toBeNull();
});
