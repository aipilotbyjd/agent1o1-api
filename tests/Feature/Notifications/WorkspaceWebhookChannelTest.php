<?php

use App\Jobs\Notifications\DeliverWorkspaceWebhookJob;
use App\Models\Notifications\NotificationChannel;
use App\Models\User;
use App\Notifications\Channels\WorkspaceWebhookChannel;
use App\Services\Http\SsrfGuard;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\Client\Request;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
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

it('hands each endpoint its own queued delivery instead of sending inline', function () {
    Bus::fake();
    Http::fake();

    $channel = NotificationChannel::factory()->create([
        'workspace_id' => $this->workspace->id,
        'config' => ['url' => 'https://hooks.example.com/hook'],
    ]);
    $workspaceId = $this->workspace->id;

    app(WorkspaceWebhookChannel::class)->send($this->owner, new class($workspaceId, $channel->id) extends Notification
    {
        public function __construct(private readonly string $workspaceId, private readonly string $channelId) {}

        public function toWorkspaceChannel(object $notifiable): array
        {
            return ['workspace_id' => $this->workspaceId, 'channel_ids' => [$this->channelId], 'message' => 'Hello'];
        }
    });

    Bus::assertDispatched(DeliverWorkspaceWebhookJob::class, fn (DeliverWorkspaceWebhookJob $job): bool => $job->channelId === $channel->id && $job->message === 'Hello');
    Http::assertNothingSent();
});

it('retries a delivery that failed with a server error', function () {
    Http::fake(['https://hooks.example.com/*' => Http::response('nope', 503)]);

    $channel = NotificationChannel::factory()->create([
        'workspace_id' => $this->workspace->id,
        'config' => ['url' => 'https://hooks.example.com/hook'],
    ]);

    expect(fn () => (new DeliverWorkspaceWebhookJob($channel->id, 'Hello'))->handle(app(WorkspaceWebhookChannel::class)))
        ->toThrow(RuntimeException::class, 'HTTP 503');
});

it('drops a delivery that failed permanently instead of retrying it', function () {
    Http::fake(['https://hooks.example.com/*' => Http::response('gone', 404)]);

    $channel = NotificationChannel::factory()->create([
        'workspace_id' => $this->workspace->id,
        'config' => ['url' => 'https://hooks.example.com/hook'],
    ]);

    (new DeliverWorkspaceWebhookJob($channel->id, 'Hello'))->handle(app(WorkspaceWebhookChannel::class));

    Http::assertSentCount(1);
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
