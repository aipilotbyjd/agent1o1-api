<?php

use App\Enums\Notifications\AlertSeverity;
use App\Models\Billing\StripeWebhookFailure;
use App\Models\SuperAdmin\AlertEvent;
use App\Models\SuperAdmin\AlertRule;
use App\Services\Notifications\AdminAlerts;
use App\Services\SuperAdmin\Alerts\AlertRuleCatalog;
use App\Services\SuperAdmin\System\QueueInspector;

/**
 * Replaces the real alert sender with one that remembers what it was asked to send.
 */
function fakeAdminAlerts(): AdminAlerts
{
    $fake = new class extends AdminAlerts
    {
        /** @var list<array{key: string, title: string, severity: AlertSeverity}> */
        public array $raised = [];

        public function raise(string $key, string $title, ?string $body = null, array $context = [], AlertSeverity $severity = AlertSeverity::Warning, ?string $throttleKey = null): bool
        {
            $this->raised[] = ['key' => $key, 'title' => $title, 'body' => $body, 'severity' => $severity];

            return true;
        }
    };

    app()->instance(AdminAlerts::class, $fake);

    return $fake;
}

function webhookRule(): AlertRule
{
    app(AlertRuleCatalog::class)->sync();

    return AlertRule::query()->where('key', 'webhook_failure')->firstOrFail();
}

function failWebhook(string $id = 'evt_one', int $attempts = 1): StripeWebhookFailure
{
    $failure = StripeWebhookFailure::record($id, 'invoice.paid', 'handler blew up');
    $failure->forceFill(['attempts' => $attempts])->save();

    return $failure;
}

it('creates the built-in rules once and keeps what an operator tuned', function () {
    app(AlertRuleCatalog::class)->sync();

    expect(AlertRule::query()->count())->toBe(count(AlertRuleCatalog::EVALUATORS));

    AlertRule::query()->where('key', 'failed_runs_spike')->update(['config' => json_encode(['min_failures' => 99])]);

    app(AlertRuleCatalog::class)->sync();

    expect(AlertRule::query()->count())->toBe(count(AlertRuleCatalog::EVALUATORS))
        ->and(AlertRule::query()->where('key', 'failed_runs_spike')->first()->config)->toBe(['min_failures' => 99]);
});

it('opens one event per problem and notifies once', function () {
    $alerts = fakeAdminAlerts();
    failWebhook();

    $this->artisan('alerts:evaluate')->assertSuccessful();
    $this->artisan('alerts:evaluate')->assertSuccessful();
    $this->artisan('alerts:evaluate')->assertSuccessful();

    $event = AlertEvent::query()->sole();

    expect($event)->status->toBe('open')
        ->and($event->fingerprint)->toBe('evt_one')
        ->and($event->occurrences)->toBe(3)
        ->and($alerts->raised)->toHaveCount(1)
        ->and($alerts->raised[0]['key'])->toBe('super-admin.alert.webhook_failure');
});

it('repeats the notification after the cooldown', function () {
    $alerts = fakeAdminAlerts();
    config(['super_admin_alerts.engine.escalate_after_minutes' => 0]);
    failWebhook();

    $this->artisan('alerts:evaluate');
    $this->travel(119)->minutes();
    $this->artisan('alerts:evaluate');

    expect($alerts->raised)->toHaveCount(1);

    $this->travel(2)->minutes();
    $this->artisan('alerts:evaluate');

    expect($alerts->raised)->toHaveCount(2);
});

it('resolves an event when the condition clears and sends a recovery notice', function () {
    $alerts = fakeAdminAlerts();
    failWebhook();

    $this->artisan('alerts:evaluate');

    StripeWebhookFailure::resolve('evt_one');
    $this->artisan('alerts:evaluate');

    $event = AlertEvent::query()->sole();

    expect($event->status)->toBe('resolved')
        ->and($event->resolved_at)->not->toBeNull()
        ->and($alerts->raised)->toHaveCount(2)
        ->and($alerts->raised[1]['title'])->toStartWith('Resolved:')
        ->and($alerts->raised[1]['severity'])->toBe(AlertSeverity::Info);
});

it('opens a fresh event if the problem comes back after resolving', function () {
    fakeAdminAlerts();
    failWebhook();

    $this->artisan('alerts:evaluate');
    StripeWebhookFailure::resolve('evt_one');
    $this->artisan('alerts:evaluate');
    failWebhook();
    $this->artisan('alerts:evaluate');

    expect(AlertEvent::query()->count())->toBe(2)
        ->and(AlertEvent::query()->active()->count())->toBe(1);
});

it('stops repeating once an alert is acknowledged', function () {
    $alerts = fakeAdminAlerts();
    failWebhook();

    $this->artisan('alerts:evaluate');
    AlertEvent::query()->sole()->forceFill(['status' => AlertEvent::STATUS_ACKNOWLEDGED, 'acknowledged_at' => now()])->save();

    $this->travel(6)->hours();
    $this->artisan('alerts:evaluate');

    expect($alerts->raised)->toHaveCount(1);
});

it('escalates an alert nobody acknowledged', function () {
    $alerts = fakeAdminAlerts();
    failWebhook();

    $this->artisan('alerts:evaluate');
    $this->travel(61)->minutes();
    $this->artisan('alerts:evaluate');
    $this->travel(1)->minute();
    $this->artisan('alerts:evaluate');

    expect($alerts->raised)->toHaveCount(2)
        ->and($alerts->raised[1]['title'])->toStartWith('Unacknowledged:')
        ->and($alerts->raised[1]['severity'])->toBe(AlertSeverity::Critical)
        ->and(AlertEvent::query()->sole()->escalated_at)->not->toBeNull();
});

it('records but does not announce while a rule is snoozed', function () {
    $alerts = fakeAdminAlerts();
    webhookRule()->forceFill(['snoozed_until' => now()->addHour()])->save();
    failWebhook();

    $this->artisan('alerts:evaluate');

    expect(AlertEvent::query()->count())->toBe(1)->and($alerts->raised)->toBeEmpty();
});

it('does not evaluate a disabled rule', function () {
    $alerts = fakeAdminAlerts();
    webhookRule()->forceFill(['enabled' => false])->save();
    failWebhook();

    $this->artisan('alerts:evaluate');

    expect(AlertEvent::query()->count())->toBe(0)->and($alerts->raised)->toBeEmpty();
});

it('holds back warnings during quiet hours but never critical alerts', function () {
    $alerts = fakeAdminAlerts();
    config(['super_admin_alerts.engine.quiet_hours' => ['enabled' => true, 'start' => '00:00', 'end' => '23:59', 'timezone' => 'UTC']]);

    failWebhook('evt_warning', 1);
    $this->artisan('alerts:evaluate');

    expect($alerts->raised)->toBeEmpty();

    failWebhook('evt_critical', 5);
    $this->artisan('alerts:evaluate');

    expect($alerts->raised)->toHaveCount(1)->and($alerts->raised[0]['severity'])->toBe(AlertSeverity::Critical);

    // The held-back warning goes out once quiet hours end.
    config(['super_admin_alerts.engine.quiet_hours.enabled' => false]);
    $this->artisan('alerts:evaluate');

    expect($alerts->raised)->toHaveCount(2);
});

it('groups a burst of alerts into one digest', function () {
    $alerts = fakeAdminAlerts();

    foreach (range(1, 5) as $i) {
        failWebhook("evt_{$i}");
    }

    $this->artisan('alerts:evaluate');

    expect(AlertEvent::query()->count())->toBe(5)
        ->and($alerts->raised)->toHaveCount(1)
        ->and($alerts->raised[0]['key'])->toBe('super-admin.alert.digest')
        ->and($alerts->raised[0]['title'])->toBe('5 alerts need attention');
});

it('survives a rule that throws, and says monitoring is broken', function () {
    $alerts = fakeAdminAlerts();
    failWebhook();

    app()->bind(QueueInspector::class, fn () => throw new RuntimeException('queue table is gone'));

    $this->artisan('alerts:evaluate')->assertFailed();

    expect(AlertEvent::query()->where('fingerprint', 'evt_one')->exists())->toBeTrue()
        ->and(collect($alerts->raised)->pluck('key'))->toContain('super-admin.alert.monitoring_failed');
});

it('sends a daily digest only when something is open', function () {
    $alerts = fakeAdminAlerts();

    $this->artisan('alerts:digest')->assertSuccessful();
    expect($alerts->raised)->toBeEmpty();

    AlertEvent::factory()->create();
    AlertEvent::factory()->resolved()->create();

    $this->artisan('alerts:digest')->assertSuccessful();
    expect($alerts->raised)->toHaveCount(1)->and($alerts->raised[0]['title'])->toBe('1 alert(s) still open');
});

it('prunes old resolved events only', function () {
    $old = AlertEvent::factory()->resolved()->create(['resolved_at' => now()->subDays(120)]);
    $recent = AlertEvent::factory()->resolved()->create();
    $open = AlertEvent::factory()->create(['first_fired_at' => now()->subDays(200)]);

    $this->artisan('alerts:prune')->assertSuccessful();

    expect(AlertEvent::query()->whereKey($old->id)->exists())->toBeFalse()
        ->and(AlertEvent::query()->whereKey($recent->id)->exists())->toBeTrue()
        ->and(AlertEvent::query()->whereKey($open->id)->exists())->toBeTrue();
});
