<?php

use App\Enums\Role;
use App\Models\Credential;
use App\Models\Trigger;
use App\Models\TriggerEvent;
use App\Models\TriggerType;
use App\Models\User;
use App\Models\Workflow;
use App\Models\Workspace;
use Database\Seeders\PlanSeeder;
use Database\Seeders\TriggerCategorySeeder;
use Database\Seeders\TriggerTypeFieldSeeder;
use Database\Seeders\TriggerTypeSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed([
        PlanSeeder::class,
        TriggerCategorySeeder::class,
        TriggerTypeSeeder::class,
        TriggerTypeFieldSeeder::class,
    ]);

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

function githubType(): TriggerType
{
    return TriggerType::where('slug', 'github_push')->firstOrFail();
}

test('creating a github trigger sets the provider, type and stores field values', function () {
    Http::fake(['api.github.com/*' => Http::response(['id' => 999], 201)]);

    $credential = Credential::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
        'data' => encrypt(json_encode(['type' => 'bearer', 'access_token' => 'ghtoken'])),
    ]);

    $response = $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/workflows/{$this->workflow->id}/triggers", [
            'name' => 'On Push',
            'trigger_type_id' => githubType()->id,
            'credential_id' => $credential->id,
            'field_values' => ['owner' => 'acme', 'repo' => 'app'],
        ])
        ->assertCreated()
        ->assertJsonPath('data.type', 'webhook')
        ->assertJsonPath('data.webhook_provider', 'github');

    $trigger = Trigger::find($response->json('data.id'));

    expect($trigger->trigger_type_id)->toBe(githubType()->id)
        ->and($trigger->getFieldValue('owner'))->toBe('acme')
        ->and($trigger->webhook_uuid)->not->toBeNull();
});

test('creating a github trigger without required fields fails validation', function () {
    $credential = Credential::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
    ]);

    $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/workflows/{$this->workflow->id}/triggers", [
            'name' => 'On Push',
            'trigger_type_id' => githubType()->id,
            'credential_id' => $credential->id,
            'field_values' => ['owner' => 'acme'], // missing 'repo'
        ])
        ->assertStatus(422);
});

test('creating a gmail trigger produces a polling trigger wired to the gmail executor', function () {
    $credential = Credential::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
    ]);

    $gmail = TriggerType::where('slug', 'gmail_new_email')->firstOrFail();

    $response = $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/workflows/{$this->workflow->id}/triggers", [
            'name' => 'New Email',
            'trigger_type_id' => $gmail->id,
            'credential_id' => $credential->id,
            'field_values' => ['search_query' => 'from:support@example.com'],
        ])
        ->assertCreated()
        ->assertJsonPath('data.type', 'polling');

    $trigger = Trigger::find($response->json('data.id'));

    expect($trigger->settings['polling_provider'])->toBe('gmail')
        ->and($trigger->polling_next_check_at)->not->toBeNull();
});

test('an incoming github webhook is verified, normalized and queued', function () {
    Queue::fake();

    $trigger = Trigger::factory()->appWebhook('github', githubType()->id)->create([
        'workflow_id' => $this->workflow->id,
        'workspace_id' => $this->workspace->id,
        'webhook_secret' => 'mysecret',
    ]);

    $payload = json_encode([
        'ref' => 'refs/heads/main',
        'commits' => [],
        'repository' => ['full_name' => 'acme/app'],
    ]);
    $signature = 'sha256='.hash_hmac('sha256', $payload, 'mysecret');

    $this->call('POST', "/api/v1/webhooks/{$trigger->webhook_uuid}", [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => $signature,
        'HTTP_X_GITHUB_EVENT' => 'push',
        'HTTP_X_GITHUB_DELIVERY' => 'delivery-1',
    ], $payload)->assertStatus(202);

    $event = TriggerEvent::first();

    expect($event->provider)->toBe('github')
        ->and($event->provider_event)->toBe('push')
        ->and($event->dedup_key)->toBe('delivery-1')
        ->and($event->event_data['trigger']['provider'])->toBe('github')
        ->and($event->event_data['data']['ref'])->toBe('refs/heads/main');
});

test('an incoming github webhook with a bad signature is rejected', function () {
    $trigger = Trigger::factory()->appWebhook('github', githubType()->id)->create([
        'workflow_id' => $this->workflow->id,
        'workspace_id' => $this->workspace->id,
        'webhook_secret' => 'mysecret',
    ]);

    $this->postJson("/api/v1/webhooks/{$trigger->webhook_uuid}", ['ref' => 'x'], [
        'X-Hub-Signature-256' => 'sha256=wrong',
        'X-GitHub-Event' => 'push',
    ])->assertUnauthorized();

    expect(TriggerEvent::count())->toBe(0);
});

test('duplicate github deliveries are de-duplicated by delivery id', function () {
    Queue::fake();

    $trigger = Trigger::factory()->appWebhook('github', githubType()->id)->create([
        'workflow_id' => $this->workflow->id,
        'workspace_id' => $this->workspace->id,
        'webhook_secret' => 'mysecret',
    ]);

    $payload = json_encode(['ref' => 'refs/heads/main', 'repository' => ['full_name' => 'acme/app']]);
    $signature = 'sha256='.hash_hmac('sha256', $payload, 'mysecret');
    $headers = [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => $signature,
        'HTTP_X_GITHUB_EVENT' => 'push',
        'HTTP_X_GITHUB_DELIVERY' => 'delivery-dup',
    ];

    $this->call('POST', "/api/v1/webhooks/{$trigger->webhook_uuid}", [], [], [], $headers, $payload)
        ->assertStatus(202);
    $this->call('POST', "/api/v1/webhooks/{$trigger->webhook_uuid}", [], [], [], $headers, $payload)
        ->assertStatus(200);

    expect(TriggerEvent::count())->toBe(1);
});
