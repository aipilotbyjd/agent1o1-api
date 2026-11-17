<?php

use App\Enums\Notifications\DeliveryStatus;
use App\Jobs\Notifications\DeliverIntegrationJob;
use App\Models\Notifications\NotificationChannel;
use App\Models\Notifications\NotificationDelivery;
use App\Models\User;
use App\Notifications\Workspace\IntegrationChannelDisabledNotification;
use App\Services\Http\SsrfGuard;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);

    app()->instance(SsrfGuard::class, new SsrfGuard(fn (string $host) => $host === 'hooks.example.com' ? ['93.184.216.34'] : []));

    Passport::actingAs($this->owner);
});

it('rejects a notification channel URL that points at an internal address', function (string $url) {
    $this->postJson("/api/v1/workspaces/{$this->workspace->id}/notification-channels", [
        'type' => 'webhook',
        'name' => 'Internal',
        'config' => ['url' => $url],
    ])->assertUnprocessable()->assertJsonValidationErrors('config.url');

    expect(NotificationChannel::query()->count())->toBe(0);
})->with([
    'loopback' => 'http://127.0.0.1:6379/',
    'metadata' => 'http://169.254.169.254/latest/meta-data/',
    'private network' => 'http://10.0.0.5/hook',
    'localhost' => 'http://localhost/hook',
    'credentials' => 'https://user:pass@hooks.example.com/hook',
]);

it('rejects pointing an existing channel at an internal address', function () {
    $channel = NotificationChannel::factory()->create([
        'workspace_id' => $this->workspace->id,
        'config' => ['url' => 'https://hooks.example.com/hook'],
    ]);

    $this->patchJson("/api/v1/workspaces/{$this->workspace->id}/notification-channels/{$channel->id}", [
        'config' => ['url' => 'http://192.168.0.1/admin'],
    ])->assertUnprocessable()->assertJsonValidationErrors('config.url');

    expect($channel->fresh()->config['url'])->toBe('https://hooks.example.com/hook');
});

it('accepts a public notification channel URL', function () {
    $this->postJson("/api/v1/workspaces/{$this->workspace->id}/notification-channels", [
        'type' => 'webhook',
        'name' => 'Public',
        'config' => ['url' => 'https://hooks.example.com/hook'],
    ])->assertCreated();
});

it('delivers a test notification to a public webhook', function () {
    Http::fake(['https://hooks.example.com/*' => Http::response('ok')]);

    $channel = NotificationChannel::factory()->create([
        'workspace_id' => $this->workspace->id,
        'config' => ['url' => 'https://hooks.example.com/hook', 'headers' => ['X-Token' => 'abc']],
    ]);

    $this->postJson("/api/v1/workspaces/{$this->workspace->id}/notification-channels/{$channel->id}/test")
        ->assertOk();

    Http::assertSent(fn (Request $request) => $request->url() === 'https://hooks.example.com/hook'
        && $request->header('X-Token') === ['abc']);
});

it('refuses to deliver to an internal address stored before validation existed', function () {
    Http::fake();

    $channel = NotificationChannel::factory()->create([
        'workspace_id' => $this->workspace->id,
        'config' => ['url' => 'http://169.254.169.254/latest/meta-data/'],
    ]);

    $this->postJson("/api/v1/workspaces/{$this->workspace->id}/notification-channels/{$channel->id}/test")
        ->assertStatus(400)
        ->assertJsonPath('message', fn (string $message) => str_contains($message, 'blocked'));

    Http::assertNothingSent();
});

it('refuses to deliver when the host resolves to a private address', function () {
    Http::fake();

    $channel = NotificationChannel::factory()->create([
        'workspace_id' => $this->workspace->id,
        'config' => ['url' => 'https://rebind.example.com/hook'],
    ]);

    $this->postJson("/api/v1/workspaces/{$this->workspace->id}/notification-channels/{$channel->id}/test")
        ->assertStatus(400);

    Http::assertNothingSent();
});

it('reports a failed delivery by status code', function () {
    Http::fake(['https://hooks.example.com/*' => Http::response('nope', 500)]);

    $channel = NotificationChannel::factory()->create([
        'workspace_id' => $this->workspace->id,
        'config' => ['url' => 'https://hooks.example.com/hook'],
    ]);

    $this->postJson("/api/v1/workspaces/{$this->workspace->id}/notification-channels/{$channel->id}/test")
        ->assertStatus(400)
        ->assertJsonPath('message', 'Delivery failed: HTTP 500.');
});

it('retries a delivery that failed with a server error', function () {
    Http::fake(['https://hooks.example.com/*' => Http::response('nope', 503)]);

    $channel = NotificationChannel::factory()->create([
        'workspace_id' => $this->workspace->id,
        'config' => ['url' => 'https://hooks.example.com/hook'],
    ]);
    $delivery = NotificationDelivery::factory()->create(['notification_channel_id' => $channel->id]);

    expect(fn () => app()->call([new DeliverIntegrationJob($delivery->id), 'handle']))
        ->toThrow(RuntimeException::class, 'HTTP 503');

    expect($delivery->fresh())->status->toBe(DeliveryStatus::Pending)->attempts->toBe(1);
});

it('drops a delivery that failed permanently instead of retrying it', function () {
    Http::fake(['https://hooks.example.com/*' => Http::response('gone', 404)]);

    $channel = NotificationChannel::factory()->create([
        'workspace_id' => $this->workspace->id,
        'config' => ['url' => 'https://hooks.example.com/hook'],
    ]);
    $delivery = NotificationDelivery::factory()->create(['notification_channel_id' => $channel->id]);

    app()->call([new DeliverIntegrationJob($delivery->id), 'handle']);

    Http::assertSentCount(1);
    expect($delivery->fresh())->status->toBe(DeliveryStatus::Failed)->error->toBe('Delivery failed: HTTP 404.')
        ->and($channel->fresh()->consecutive_failures)->toBe(1);
});

it('signs a webhook delivery with the channel secret and a timestamp', function () {
    Http::fake(['https://hooks.example.com/*' => Http::response('ok')]);

    $channel = NotificationChannel::factory()->create([
        'workspace_id' => $this->workspace->id,
        'config' => ['url' => 'https://hooks.example.com/hook'],
    ]);
    $delivery = NotificationDelivery::factory()->create([
        'notification_channel_id' => $channel->id,
        'payload' => ['id' => 'evt', 'type' => 'run.failed', 'message' => 'Hello'],
    ]);

    app()->call([new DeliverIntegrationJob($delivery->id), 'handle']);

    Http::assertSent(function (Request $request) use ($channel): bool {
        $timestamp = $request->header('X-Notification-Timestamp')[0] ?? '';
        $expected = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$request->body(), $channel->signing_secret);

        return $request->header('X-Notification-Signature') === [$expected];
    });
    expect($delivery->fresh()->status)->toBe(DeliveryStatus::Sent)
        ->and($channel->fresh()->last_delivered_at)->not->toBeNull();
});

it('switches a channel off after too many failures in a row and tells the admins', function () {
    Http::fake(['https://hooks.example.com/*' => Http::response('gone', 410)]);
    Notification::fake();
    config(['notifications.integrations.disable_after_failures' => 2]);

    $channel = NotificationChannel::factory()->create([
        'workspace_id' => $this->workspace->id,
        'config' => ['url' => 'https://hooks.example.com/hook'],
        'consecutive_failures' => 1,
    ]);
    $delivery = NotificationDelivery::factory()->create(['notification_channel_id' => $channel->id]);

    app()->call([new DeliverIntegrationJob($delivery->id), 'handle']);

    expect($channel->fresh())->is_active->toBeFalse()->disabled_at->not->toBeNull();
    Notification::assertSentTo($this->owner, IntegrationChannelDisabledNotification::class);
});

it('skips a delivery whose channel was switched off meanwhile', function () {
    Http::fake();

    $channel = NotificationChannel::factory()->create(['workspace_id' => $this->workspace->id, 'is_active' => false]);
    $delivery = NotificationDelivery::factory()->create(['notification_channel_id' => $channel->id]);

    app()->call([new DeliverIntegrationJob($delivery->id), 'handle']);

    Http::assertNothingSent();
    expect($delivery->fresh()->status)->toBe(DeliveryStatus::Skipped);
});

it('keeps a notification channel when the member who created it is deleted', function () {
    $creator = User::factory()->create();
    $channel = NotificationChannel::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $creator->id,
    ]);

    $creator->delete();

    expect($channel->fresh())->not->toBeNull()
        ->and($channel->fresh()->created_by)->toBeNull();
});
