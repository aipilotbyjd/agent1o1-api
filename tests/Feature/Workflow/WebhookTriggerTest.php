<?php

use App\Jobs\ProcessTriggerEventJob;
use App\Models\Trigger;
use App\Models\TriggerEvent;
use App\Models\User;
use App\Models\Workflow;
use App\Models\Workspace;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed(PlanSeeder::class);

    $user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $this->workflow = Workflow::factory()->active()->withSimpleGraph()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $user->id,
    ]);
});

test('incoming webhook creates a trigger event and queues processing', function () {
    Queue::fake();

    $trigger = Trigger::factory()->webhook()->create([
        'workflow_id' => $this->workflow->id,
        'workspace_id' => $this->workspace->id,
        'webhook_secret' => null,
    ]);

    $response = $this->postJson("/api/v1/webhooks/{$trigger->webhook_uuid}", [
        'order_id' => 42,
    ]);

    $response->assertStatus(202);

    $event = TriggerEvent::first();
    expect($event)->not->toBeNull()
        ->and($event->event_data['body'])->toBe(['order_id' => 42])
        ->and($trigger->fresh()->total_events)->toBe(1);

    Queue::assertPushedOn('triggers', ProcessTriggerEventJob::class);
});

test('unknown webhook uuid returns 404', function () {
    $this->postJson('/api/v1/webhooks/'.fake()->uuid())
        ->assertNotFound();
});

test('paused trigger webhook returns 404', function () {
    $trigger = Trigger::factory()->webhook()->create([
        'workflow_id' => $this->workflow->id,
        'workspace_id' => $this->workspace->id,
        'is_paused' => true,
    ]);

    $this->postJson("/api/v1/webhooks/{$trigger->webhook_uuid}")
        ->assertNotFound();
});

test('webhook with secret rejects invalid signatures', function () {
    $trigger = Trigger::factory()->webhook()->create([
        'workflow_id' => $this->workflow->id,
        'workspace_id' => $this->workspace->id,
        'webhook_secret' => 'topsecret',
    ]);

    $this->postJson("/api/v1/webhooks/{$trigger->webhook_uuid}", ['x' => 1], [
        'X-Webhook-Signature' => 'wrong',
    ])->assertUnauthorized();
});

test('webhook with secret accepts valid signatures', function () {
    Queue::fake();

    $trigger = Trigger::factory()->webhook()->create([
        'workflow_id' => $this->workflow->id,
        'workspace_id' => $this->workspace->id,
        'webhook_secret' => 'topsecret',
    ]);

    $payload = json_encode(['x' => 1]);
    $signature = hash_hmac('sha256', $payload, 'topsecret');

    $this->call('POST', "/api/v1/webhooks/{$trigger->webhook_uuid}", [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_WEBHOOK_SIGNATURE' => $signature,
    ], $payload)->assertStatus(202);
});
