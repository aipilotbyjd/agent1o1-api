<?php

use App\Enums\SuperAdmin\SuperAdminRole;
use App\Models\SuperAdmin\AlertEvent;
use App\Models\SuperAdmin\AlertRule;
use App\Models\SuperAdmin\SuperAdminAuditLog;
use App\Notifications\Admin\AdminAlertNotification;
use App\Services\SuperAdmin\Alerts\AlertRuleCatalog;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->admin = superAdmin(SuperAdminRole::Operator);
    $this->headers = superAdminHeaders($this->admin);
});

it('lets every role read alerts but only operators and owners change them', function (SuperAdminRole $role, int $read, int $write) {
    $headers = superAdminHeaders(superAdmin($role));
    $event = AlertEvent::factory()->create();

    $this->withHeaders($headers)->getJson(saUrl('alerts'))->assertStatus($read);
    $this->withHeaders($headers)->getJson(saUrl('alerts/summary'))->assertStatus($read);
    $this->withHeaders($headers)->postJson(saUrl("alerts/{$event->id}/acknowledge"))->assertStatus($write);
})->with([
    'owner' => [SuperAdminRole::Owner, 200, 200],
    'operator' => [SuperAdminRole::Operator, 200, 200],
    'finance' => [SuperAdminRole::Finance, 200, 403],
    'support' => [SuperAdminRole::Support, 200, 403],
    'viewer' => [SuperAdminRole::Viewer, 200, 403],
]);

it('lists alerts, active ones first, and filters them', function () {
    $critical = AlertEvent::factory()->create(['severity' => 'critical']);
    AlertEvent::factory()->create(['severity' => 'info']);
    AlertEvent::factory()->resolved()->create(['severity' => 'critical']);

    $this->withHeaders($this->headers)->getJson(saUrl('alerts'))->assertOk()->assertJsonPath('meta.total', 3);
    $this->withHeaders($this->headers)->getJson(saUrl('alerts?status=resolved'))->assertJsonPath('meta.total', 1);
    $this->withHeaders($this->headers)->getJson(saUrl('alerts?status=active&severity=critical'))->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $critical->id);
    $this->withHeaders($this->headers)->getJson(saUrl('alerts?severity=bogus'))->assertUnprocessable();
    $this->withHeaders($this->headers)->getJson(saUrl("alerts/{$critical->id}"))->assertOk()->assertJsonPath('data.severity', 'critical');
});

it('acknowledges and resolves an alert, with an audit trail', function () {
    $event = AlertEvent::factory()->create();

    $this->withHeaders($this->headers)->postJson(saUrl("alerts/{$event->id}/acknowledge"))
        ->assertOk()
        ->assertJsonPath('data.status', 'acknowledged')
        ->assertJsonPath('data.acknowledged_by', $this->admin->id);

    $this->withHeaders($this->headers)->postJson(saUrl("alerts/{$event->id}/resolve"))->assertOk()->assertJsonPath('data.status', 'resolved');
    $this->withHeaders($this->headers)->postJson(saUrl("alerts/{$event->id}/resolve"))->assertStatus(409);
    $this->withHeaders($this->headers)->postJson(saUrl("alerts/{$event->id}/acknowledge"))->assertStatus(409);

    expect(SuperAdminAuditLog::query()->whereIn('action', ['alerts.acknowledged', 'alerts.resolved'])->count())->toBe(2);
});

it('summarises open alerts, time to acknowledge and the noisiest rules', function () {
    $noisy = AlertRule::factory()->create(['name' => 'Noisy rule']);
    $quiet = AlertRule::factory()->create();

    AlertEvent::factory()->for($noisy, 'rule')->create(['occurrences' => 40, 'first_fired_at' => now()->subHour(), 'acknowledged_at' => now()->subMinutes(50), 'status' => 'acknowledged']);
    AlertEvent::factory()->for($noisy, 'rule')->resolved()->create(['occurrences' => 10]);
    AlertEvent::factory()->for($quiet, 'rule')->create(['severity' => 'critical']);

    $data = $this->withHeaders($this->headers)->getJson(saUrl('alerts/summary'))->assertOk()->json('data');

    expect($data['open'])->toMatchArray(['total' => 2, 'unacknowledged' => 1, 'by_severity' => ['warning' => 1, 'critical' => 1]])
        ->and($data['mean_seconds_to_acknowledge'])->toBe(600)
        ->and($data['noisiest_rules'][0])->toMatchArray(['name' => 'Noisy rule', 'events' => 2, 'fires' => 50, 'never_acted_on' => 1]);
});

it('lists the built-in rules with their default thresholds', function () {
    $rules = collect($this->withHeaders($this->headers)->getJson(saUrl('alerts/rules'))->assertOk()->json('data'))->keyBy('key');

    expect($rules)->toHaveCount(count(AlertRuleCatalog::EVALUATORS))
        ->and($rules['failed_runs_spike']['config'])->toMatchArray(['min_failures' => 5, 'factor' => 3]);
});

it('tunes a rule, refusing settings it does not have', function () {
    app(AlertRuleCatalog::class)->sync();
    $rule = AlertRule::query()->where('key', 'failed_runs_spike')->firstOrFail();

    $this->withHeaders($this->headers)->patchJson(saUrl("alerts/rules/{$rule->id}"), ['config' => ['min_failures' => 20]])->assertUnprocessable()->assertJsonValidationErrors('reason');

    $this->withHeaders($this->headers)->patchJson(saUrl("alerts/rules/{$rule->id}"), ['reason' => 'Too noisy at night', 'config' => ['nonsense' => 1]])->assertUnprocessable()->assertJsonValidationErrors('config.nonsense');
    $this->withHeaders($this->headers)->patchJson(saUrl("alerts/rules/{$rule->id}"), ['reason' => 'Too noisy at night', 'config' => ['min_failures' => 'lots']])->assertUnprocessable();
    $this->withHeaders($this->headers)->patchJson(saUrl("alerts/rules/{$rule->id}"), ['reason' => 'Too noisy at night', 'severity' => 'urgent'])->assertUnprocessable();

    $this->withHeaders($this->headers)->patchJson(saUrl("alerts/rules/{$rule->id}"), [
        'reason' => 'Too noisy at night', 'config' => ['min_failures' => 20], 'severity' => 'critical', 'cooldown_minutes' => 15, 'enabled' => false,
    ])->assertOk()->assertJsonPath('data.config.min_failures', 20)->assertJsonPath('data.config.factor', 3);

    $rule->refresh();

    expect($rule->severity->value)->toBe('critical')
        ->and($rule->cooldown_minutes)->toBe(15)
        ->and($rule->enabled)->toBeFalse()
        ->and($rule->config['min_failures'])->toBe(20)
        ->and(SuperAdminAuditLog::query()->where('action', 'alerts.rule_updated')->count())->toBe(1);
});

it('snoozes and un-snoozes a rule', function () {
    $rule = AlertRule::factory()->create();

    $this->withHeaders($this->headers)->postJson(saUrl("alerts/rules/{$rule->id}/snooze"), ['reason' => 'Planned maintenance window', 'minutes' => 120])
        ->assertOk()->assertJsonPath('data.is_snoozed', true);

    expect($rule->refresh()->isSnoozed())->toBeTrue();

    $this->withHeaders($this->headers)->postJson(saUrl("alerts/rules/{$rule->id}/snooze"), ['reason' => 'Maintenance finished early', 'minutes' => 0])
        ->assertOk()->assertJsonPath('data.is_snoozed', false);

    $this->withHeaders($this->headers)->postJson(saUrl("alerts/rules/{$rule->id}/snooze"), ['reason' => 'A month is too long', 'minutes' => 99999])->assertUnprocessable();
});

it('sends a test notification', function () {
    $rule = AlertRule::factory()->create();

    config(['admin_alerts.recipients.mail' => ['ops@example.test'], 'admin_alerts.routing.warning' => ['mail']]);
    Notification::fake();

    $this->withHeaders($this->headers)->postJson(saUrl("alerts/rules/{$rule->id}/test"))->assertOk()->assertJsonPath('data.dispatched', true);

    Notification::assertSentOnDemand(AdminAlertNotification::class);
});

it('says so when a test notification has nowhere to go', function () {
    $rule = AlertRule::factory()->create();

    config(['admin_alerts.recipients' => ['mail' => [], 'discord' => null, 'webhook' => null]]);

    $this->withHeaders($this->headers)->postJson(saUrl("alerts/rules/{$rule->id}/test"))->assertOk()->assertJsonPath('data.dispatched', false);
});
