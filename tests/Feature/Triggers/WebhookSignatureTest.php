<?php

use App\Enums\Triggers\TriggerEventStatus;
use App\Models\Triggers\Trigger;
use App\Models\Triggers\TriggerPreset;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Queue;

function makeSignedTrigger(string $scheme): Trigger
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    $preset = TriggerPreset::factory()->create(['signature_scheme' => $scheme]);

    return Trigger::factory()->webhook()->forWorkspace($workspace)->create([
        'preset_id' => $preset->id,
        'signing_secret' => 'top-secret',
    ]);
}

it('accepts a valid GitHub signature', function () {
    Queue::fake();
    $trigger = makeSignedTrigger('github');
    $body = json_encode(['action' => 'opened']);
    $signature = 'sha256='.hash_hmac('sha256', $body, 'top-secret');

    $this->postJson("/api/hooks/{$trigger->token}", json_decode($body, true), [
        'X-Hub-Signature-256' => $signature,
    ])->assertOk();

    expect($trigger->events()->first()->status)->toBe(TriggerEventStatus::Queued);
});

it('rejects a tampered GitHub body and writes a rejected event without a delivery id', function () {
    $trigger = makeSignedTrigger('github');
    $signature = 'sha256='.hash_hmac('sha256', 'original-body', 'top-secret');

    $this->postJson("/api/hooks/{$trigger->token}", ['tampered' => true], [
        'X-Hub-Signature-256' => $signature,
    ])->assertStatus(401);

    $event = $trigger->events()->first();

    expect($event->status)->toBe(TriggerEventStatus::Rejected);
    expect($event->delivery_id)->toBeNull();
});

it('accepts a valid Stripe signature', function () {
    Queue::fake();
    $trigger = makeSignedTrigger('stripe');
    $body = json_encode(['id' => 'evt_1']);
    $timestamp = time();
    $signature = hash_hmac('sha256', "{$timestamp}.{$body}", 'top-secret');

    $this->postJson("/api/hooks/{$trigger->token}", json_decode($body, true), [
        'Stripe-Signature' => "t={$timestamp},v1={$signature}",
    ])->assertOk();

    expect($trigger->events()->first()->status)->toBe(TriggerEventStatus::Queued);
});

it('rejects a stale Stripe timestamp', function () {
    $trigger = makeSignedTrigger('stripe');
    $body = json_encode(['id' => 'evt_1']);
    $timestamp = time() - 600;
    $signature = hash_hmac('sha256', "{$timestamp}.{$body}", 'top-secret');

    $this->postJson("/api/hooks/{$trigger->token}", json_decode($body, true), [
        'Stripe-Signature' => "t={$timestamp},v1={$signature}",
    ])->assertStatus(401);
});

it('accepts a valid Slack signature', function () {
    Queue::fake();
    $trigger = makeSignedTrigger('slack');
    $body = json_encode(['event_id' => 'ev_1']);
    $timestamp = time();
    $signature = 'v0='.hash_hmac('sha256', "v0:{$timestamp}:{$body}", 'top-secret');

    $this->postJson("/api/hooks/{$trigger->token}", json_decode($body, true), [
        'X-Slack-Request-Timestamp' => (string) $timestamp,
        'X-Slack-Signature' => $signature,
    ])->assertOk();

    expect($trigger->events()->first()->status)->toBe(TriggerEventStatus::Queued);
});

function makeGenericSignedTrigger(?string $secret): Trigger
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    return Trigger::factory()->webhook()->forWorkspace($workspace)->create(['signing_secret' => $secret]);
}

it('enforces a signing secret on a trigger with no provider preset', function () {
    Queue::fake();
    $trigger = makeGenericSignedTrigger('top-secret');
    $body = json_encode(['hello' => 'world']);

    $this->postJson("/api/hooks/{$trigger->token}", json_decode($body, true), [
        'X-Signature-256' => 'sha256='.hash_hmac('sha256', $body, 'top-secret'),
    ])->assertOk();

    expect($trigger->events()->first()->status)->toBe(TriggerEventStatus::Queued);
});

it('rejects an unsigned or wrongly signed post to a trigger that has a signing secret', function (string $case) {
    $trigger = makeGenericSignedTrigger('top-secret');
    $body = json_encode(['hello' => 'world']);

    $headers = match ($case) {
        'no header' => [],
        'garbage signature' => ['X-Signature-256' => 'sha256=00'],
        'signed with another secret' => ['X-Signature-256' => 'sha256='.hash_hmac('sha256', $body, 'other-secret')],
        'signed over another body' => ['X-Signature-256' => 'sha256='.hash_hmac('sha256', '{"hello":"tampered"}', 'top-secret')],
    };

    $this->postJson("/api/hooks/{$trigger->token}", json_decode($body, true), $headers)->assertStatus(401);

    expect($trigger->events()->first()->status)->toBe(TriggerEventStatus::Rejected);
})->with(['no header', 'garbage signature', 'signed with another secret', 'signed over another body']);

it('still accepts unsigned posts to a trigger with no signing secret', function () {
    Queue::fake();
    $trigger = makeGenericSignedTrigger(null);

    $this->postJson("/api/hooks/{$trigger->token}", ['hello' => 'world'])->assertOk();
});
