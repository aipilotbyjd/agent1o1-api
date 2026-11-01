<?php

use App\Enums\Notifications\AlertSeverity;
use App\Enums\Notifications\NotificationEvent;
use App\Notifications\Admin\AdminAlertNotification;
use App\Notifications\Channels\AdminAlertChannel;
use App\Services\Notifications\AdminAlerts;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    config()->set('admin_alerts.enabled', true);
    config()->set('admin_alerts.recipients.discord', 'https://discord.test/webhook');
    config()->set('admin_alerts.recipients.webhook', 'https://ops.test/hook');
    config()->set('admin_alerts.recipients.mail', ['ops@example.com']);
    config()->set('admin_alerts.webhook_secret', null);
    config()->set('admin_alerts.throttle_seconds', 3600);
});

it('delivers a warning alert to discord and the generic webhook', function () {
    Http::preventStrayRequests();
    Http::fake(['*' => Http::response('', 204)]);

    $sent = app(AdminAlerts::class)->raise(
        key: 'usage.threshold_crossed',
        title: 'Acme crossed 80% of its credit limit',
        body: '800 of 1000 credits used.',
        context: ['workspace_id' => 7],
        severity: AlertSeverity::Warning,
    );

    expect($sent)->toBeTrue();

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://discord.test/webhook'
            && $request['embeds'][0]['title'] === 'Acme crossed 80% of its credit limit'
            && $request['embeds'][0]['color'] === AlertSeverity::Warning->color()
            && $request['embeds'][0]['fields'][0]['name'] === 'Workspace id';
    });

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://ops.test/hook'
            && $request['key'] === 'usage.threshold_crossed'
            && $request['severity'] === 'warning'
            && $request['context']['workspace_id'] === 7;
    });

    Http::assertSentCount(2);
});

it('signs generic webhook deliveries when a secret is configured', function () {
    config()->set('admin_alerts.webhook_secret', 'shh');
    config()->set('admin_alerts.routing.warning', ['webhook']);

    Http::preventStrayRequests();
    Http::fake(['*' => Http::response('', 200)]);

    app(AdminAlerts::class)->raise(key: 'ops.check', title: 'Signed');

    Http::assertSent(function (Request $request): bool {
        $expected = 'sha256='.hash_hmac('sha256', $request->body(), 'shh');

        return $request->header('X-Admin-Alert-Signature')[0] === $expected;
    });
});

it('emails only for critical alerts', function () {
    Notification::fake();

    app(AdminAlerts::class)->raise(
        key: 'usage.threshold_crossed',
        title: 'Warning alert',
        severity: AlertSeverity::Warning,
    );

    app(AdminAlerts::class)->raise(
        key: 'usage.limit_exhausted',
        title: 'Critical alert',
        severity: AlertSeverity::Critical,
    );

    Notification::assertSentOnDemand(
        AdminAlertNotification::class,
        function (AdminAlertNotification $notification, array $channels): bool {
            return $notification->key === 'usage.threshold_crossed'
                && $channels === [AdminAlertChannel::class];
        },
    );

    Notification::assertSentOnDemand(
        AdminAlertNotification::class,
        function (AdminAlertNotification $notification, array $channels, object $notifiable): bool {
            return $notification->key === 'usage.limit_exhausted'
                && $channels === [AdminAlertChannel::class, 'mail']
                && $notifiable->routeNotificationFor('mail') === ['ops@example.com'];
        },
    );
});

it('suppresses a repeat of the same alert inside the throttle window', function () {
    Notification::fake();

    $alerts = app(AdminAlerts::class);

    $first = $alerts->raise(key: 'usage.threshold_crossed', title: 'First', throttleKey: 'usage:7');
    $second = $alerts->raise(key: 'usage.threshold_crossed', title: 'Second', throttleKey: 'usage:7');

    expect($first)->toBeTrue();
    expect($second)->toBeFalse();

    Notification::assertSentOnDemandTimes(AdminAlertNotification::class, 1);
});

it('alerts separately for different throttle keys', function () {
    Notification::fake();

    $alerts = app(AdminAlerts::class);

    expect($alerts->raise(key: 'usage.threshold_crossed', title: 'A', throttleKey: 'usage:7'))->toBeTrue();
    expect($alerts->raise(key: 'usage.threshold_crossed', title: 'B', throttleKey: 'usage:8'))->toBeTrue();

    Notification::assertSentOnDemandTimes(AdminAlertNotification::class, 2);
});

it('alerts again once the throttle window has elapsed', function () {
    Notification::fake();

    $alerts = app(AdminAlerts::class);

    expect($alerts->raise(key: 'usage.threshold_crossed', title: 'First', throttleKey: 'usage:7'))->toBeTrue();

    $this->travel(61)->minutes();

    expect($alerts->raise(key: 'usage.threshold_crossed', title: 'Later', throttleKey: 'usage:7'))->toBeTrue();

    Notification::assertSentOnDemandTimes(AdminAlertNotification::class, 2);
});

it('redacts secret-looking context keys at every depth', function () {
    Notification::fake();

    app(AdminAlerts::class)->raise(
        key: 'ops.check',
        title: 'Context check',
        context: [
            'workspace_id' => 7,
            'api_token' => 'super-secret',
            'connector' => ['name' => 'Slack', 'refresh_password' => 'nope'],
        ],
    );

    Notification::assertSentOnDemand(
        AdminAlertNotification::class,
        function (AdminAlertNotification $notification): bool {
            return $notification->context === [
                'workspace_id' => 7,
                'api_token' => '[redacted]',
                'connector' => ['name' => 'Slack', 'refresh_password' => '[redacted]'],
            ];
        },
    );
});

it('sends nothing when alerting is disabled', function () {
    Notification::fake();
    config()->set('admin_alerts.enabled', false);

    expect(app(AdminAlerts::class)->raise(key: 'ops.check', title: 'Nope'))->toBeFalse();

    Notification::assertNothingSent();
});

it('sends nothing when the severity has no configured destination', function () {
    Notification::fake();
    config()->set('admin_alerts.recipients.discord', null);
    config()->set('admin_alerts.recipients.webhook', null);

    expect(app(AdminAlerts::class)->raise(
        key: 'ops.check',
        title: 'Nope',
        severity: AlertSeverity::Warning,
    ))->toBeFalse();

    Notification::assertNothingSent();
});

it('does not claim the throttle window when nothing is configured', function () {
    Notification::fake();
    config()->set('admin_alerts.recipients.discord', null);
    config()->set('admin_alerts.recipients.webhook', null);

    $alerts = app(AdminAlerts::class);

    expect($alerts->raise(key: 'ops.check', title: 'Nope', throttleKey: 'ops'))->toBeFalse();

    config()->set('admin_alerts.recipients.discord', 'https://discord.test/webhook');

    expect($alerts->raise(key: 'ops.check', title: 'Now configured', throttleKey: 'ops'))->toBeTrue();
});

it('never lets a failing destination break the caller', function () {
    Http::preventStrayRequests();
    Http::fake(['*' => Http::failedConnection()]);

    $sent = app(AdminAlerts::class)->raise(
        key: 'usage.limit_exhausted',
        title: 'Acme is blocked',
        severity: AlertSeverity::Critical,
    );

    expect($sent)->toBeTrue();
});

it('renders and addresses the mail for a critical alert', function () {
    config()->set('admin_alerts.recipients.discord', null);
    config()->set('admin_alerts.recipients.webhook', null);

    app(AdminAlerts::class)->raise(
        key: 'usage.limit_exhausted',
        title: 'Acme is blocked',
        body: 'No credits remain in the current period.',
        context: ['workspace_id' => 7],
        severity: AlertSeverity::Critical,
    );

    $messages = app('mailer')->getSymfonyTransport()->messages();

    expect($messages)->toHaveCount(1);

    $message = $messages[0]->getOriginalMessage();

    expect($message->getSubject())->toBe('[Critical] Acme is blocked');
    expect(collect($message->getTo())->map->getAddress()->all())->toBe(['ops@example.com']);
});

it('disables discord mention parsing so a workspace name cannot ping the channel', function () {
    Http::preventStrayRequests();
    Http::fake(['*' => Http::response('', 204)]);

    config()->set('admin_alerts.routing.warning', ['discord']);

    app(AdminAlerts::class)->raise(key: 'usage.threshold_crossed', title: '@everyone crossed 80%');

    Http::assertSent(fn (Request $request): bool => $request['allowed_mentions'] === ['parse' => []]);
});

it('keeps admin alert keys out of the tenant notification catalogue', function () {
    $tenantKeys = array_column(NotificationEvent::catalog(), 'key');

    expect($tenantKeys)->not->toContain('usage.threshold_crossed');
    expect($tenantKeys)->not->toContain('usage.limit_exhausted');
});
