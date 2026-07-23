<?php

use App\Enums\Role;
use App\Jobs\ProcessTriggerEventJob;
use App\Jobs\RunAgentJob;
use App\Models\Agent;
use App\Models\Trigger;
use App\Models\TriggerEvent;
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

/**
 * Build an agent-targeted webhook trigger (no secret — the UUID is the secret,
 * matching the pre-unification public agent webhook behaviour).
 */
function agentWebhookTrigger(Agent $agent, array $overrides = []): Trigger
{
    return Trigger::factory()->forAgent($agent)->create(array_merge([
        'type' => 'webhook',
        'is_active' => true,
        'webhook_uuid' => Str::uuid()->toString(),
        'webhook_secret' => null,
        'webhook_status' => 'active',
        'initial_message' => null,
    ], $overrides));
}

test('a trigger can be created for an agent', function () {
    $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/agents/{$this->agent->id}/triggers", [
            'type' => 'webhook',
            'initial_message' => 'Run please.',
        ])
        ->assertCreated()
        ->assertJsonPath('data.type', 'webhook')
        ->assertJsonPath('data.target_type', 'agent');

    expect($this->agent->triggers()->count())->toBe(1);
});

test('firing a trigger dispatches the agent job', function () {
    Queue::fake();

    $trigger = Trigger::factory()->forAgent($this->agent)->create();

    $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/agents/{$this->agent->id}/triggers/{$trigger->id}/fire")
        ->assertStatus(202);

    Queue::assertPushed(RunAgentJob::class, fn (RunAgentJob $job) => $job->agentId === $this->agent->id
        && $job->triggerId === $trigger->id);
});

test('a public agent webhook flows through the unified trigger pipeline', function () {
    // Let ProcessTriggerEventJob run synchronously; only capture the terminal
    // agent job so we can assert the message it carries.
    Queue::fake([RunAgentJob::class]);

    $trigger = agentWebhookTrigger($this->agent);

    $this->postJson("/api/v1/webhooks/{$trigger->webhook_uuid}", ['message' => 'Hello from outside'])
        ->assertStatus(202);

    expect(TriggerEvent::where('trigger_id', $trigger->id)->count())->toBe(1);

    Queue::assertPushed(RunAgentJob::class, fn (RunAgentJob $job) => $job->agentId === $this->agent->id
        && $job->message === 'Hello from outside');
});

test('a webhook for an inactive agent does not dispatch a run', function () {
    Queue::fake([RunAgentJob::class]);

    $agent = Agent::factory()->inactive()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
    ]);
    $trigger = agentWebhookTrigger($agent);

    // Ingestion is accepted (webhooks don't leak target state), but the pipeline
    // drops it because the target agent is inactive.
    $this->postJson("/api/v1/webhooks/{$trigger->webhook_uuid}", ['message' => 'hi'])
        ->assertStatus(202);

    Queue::assertNotPushed(RunAgentJob::class);
});

test('a trigger can be deleted', function () {
    $trigger = Trigger::factory()->forAgent($this->agent)->create();

    $this->actingAs($this->user, 'api')
        ->deleteJson("/api/v1/workspaces/{$this->workspace->id}/agents/{$this->agent->id}/triggers/{$trigger->id}")
        ->assertOk();

    expect(Trigger::find($trigger->id))->toBeNull();
});
