<?php

use App\Notifications\Admin\AdminAlertNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    config()->set('admin_alerts.enabled', true);
    config()->set('admin_alerts.recipients.discord', 'https://discord.test/webhook');
    config()->set('admin_alerts.recipients.webhook', null);
    config()->set('admin_alerts.recipients.mail', []);
    config()->set('admin_alerts.throttle_seconds', 3600);
});

it('sends a test alert through the configured destinations', function () {
    Notification::fake();

    $this->artisan('admin:alert-test warning')->assertSuccessful();

    Notification::assertSentOnDemand(
        AdminAlertNotification::class,
        fn (AdminAlertNotification $notification): bool => $notification->key === 'admin.alert_test',
    );
});

it('can be run repeatedly without being throttled', function () {
    Notification::fake();

    $this->artisan('admin:alert-test warning')->assertSuccessful();
    $this->artisan('admin:alert-test warning')->assertSuccessful();

    Notification::assertSentOnDemandTimes(AdminAlertNotification::class, 2);
});

it('fails when alerting is disabled', function () {
    Notification::fake();
    config()->set('admin_alerts.enabled', false);

    $this->artisan('admin:alert-test warning')->assertFailed();

    Notification::assertNothingSent();
});

it('fails when the severity has no configured destination', function () {
    Notification::fake();
    config()->set('admin_alerts.recipients.discord', null);

    $this->artisan('admin:alert-test warning')->assertFailed();

    Notification::assertNothingSent();
});

it('rejects an unknown severity', function () {
    Notification::fake();

    $this->artisan('admin:alert-test screaming')->assertFailed();

    Notification::assertNothingSent();
});
