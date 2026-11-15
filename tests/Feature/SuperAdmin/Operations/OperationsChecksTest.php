<?php

use App\Enums\Notifications\AlertSeverity;
use App\Enums\Operations\TaskStatus;
use App\Enums\System\BackupStatus;
use App\Models\Billing\StripeWebhookFailure;
use App\Models\Broadcasts\Announcement;
use App\Models\SuperAdmin\AlertEvent;
use App\Models\SuperAdmin\ScheduledCommandRun;
use App\Models\SuperAdmin\SystemHealthSnapshot;
use App\Models\System\Backup;
use App\Services\SuperAdmin\Operations\OperationsChecks;
use App\Services\SuperAdmin\System\FailedJobService;
use App\Services\SuperAdmin\System\SchedulerMonitor;
use App\Services\SuperAdmin\System\StuckWorkDetector;
use Illuminate\Support\Facades\Cache;

function check(string $key): array
{
    $result = app(OperationsChecks::class)->run($key);

    return [$result->status, $result->message];
}

it('knows every automatic check by its key', function () {
    expect(app(OperationsChecks::class)->keys())->toBe([
        'scheduler_heartbeat', 'system_health', 'backups', 'failed_jobs', 'stuck_work', 'open_alerts', 'stripe_webhooks', 'audit_chain', 'maintenance_window',
    ]);
});

it('asks for a look, without failing, when a task points to a check that does not exist or breaks', function () {
    [$status] = check('retired_check');
    expect($status)->toBe(TaskStatus::Due);

    $this->mock(FailedJobService::class)->shouldReceive('count')->andThrow(new RuntimeException('database gone'));

    [$status, $message] = check('failed_jobs');
    expect($status)->toBe(TaskStatus::Due)->and($message)->toContain('could not run');
});

it('reads the scheduler heartbeat', function (?int $secondsAgo, TaskStatus $expected) {
    Cache::forget(SchedulerMonitor::HEARTBEAT_KEY);

    if ($secondsAgo !== null) {
        Cache::forever(SchedulerMonitor::HEARTBEAT_KEY, now()->subSeconds($secondsAgo)->getTimestamp());
    }

    expect(check('scheduler_heartbeat')[0])->toBe($expected);
})->with([
    'never' => [null, TaskStatus::Overdue],
    'just now' => [30, TaskStatus::Done],
    'a bit late' => [400, TaskStatus::Due],
    'stopped' => [3600, TaskStatus::Overdue],
]);

it('reads the latest health snapshot', function (?string $status, int $minutesAgo, TaskStatus $expected) {
    if ($status !== null) {
        SystemHealthSnapshot::query()->create(['status' => $status, 'checks' => [], 'created_at' => now()->subMinutes($minutesAgo)]);
    }

    expect(check('system_health')[0])->toBe($expected);
})->with([
    'none yet' => [null, 0, TaskStatus::Due],
    'healthy' => ['healthy', 2, TaskStatus::Done],
    'degraded' => ['degraded', 2, TaskStatus::Due],
    'down' => ['down', 2, TaskStatus::Overdue],
    'stale' => ['healthy', 60, TaskStatus::Due],
]);

it('checks that a backup finished recently', function () {
    config(['backups.enabled' => false]);
    expect(check('backups')[0])->toBe(TaskStatus::Due);

    config(['backups.enabled' => true]);
    expect(check('backups')[0])->toBe(TaskStatus::Overdue);

    Backup::factory()->create(['finished_at' => now()->subHours(3)]);
    expect(check('backups')[0])->toBe(TaskStatus::Done);
});

it('fails the backup check when the newest backup failed, or the last good one is old', function () {
    config(['backups.enabled' => true]);

    Backup::factory()->create(['finished_at' => now()->subHours(40)]);
    expect(check('backups')[0])->toBe(TaskStatus::Overdue);

    Backup::factory()->create(['status' => BackupStatus::Completed, 'finished_at' => now()->subHours(3)]);
    Backup::factory()->failed()->create(['finished_at' => now()->subHour(), 'error' => 'Bucket unreachable']);

    [$status, $message] = check('backups');
    expect($status)->toBe(TaskStatus::Overdue)->and($message)->toContain('Bucket unreachable');
});

it('counts failed jobs and stuck work', function () {
    $this->mock(FailedJobService::class)->shouldReceive('count')->andReturn(0, 3, 40);
    expect(check('failed_jobs')[0])->toBe(TaskStatus::Done);
    expect(check('failed_jobs')[0])->toBe(TaskStatus::Due);
    expect(check('failed_jobs')[0])->toBe(TaskStatus::Overdue);

    $this->mock(StuckWorkDetector::class)->shouldReceive('report')->andReturn(['total' => 0, 'groups' => []], ['total' => 2, 'groups' => []], ['total' => 15, 'groups' => []]);
    expect(check('stuck_work')[0])->toBe(TaskStatus::Done);
    expect(check('stuck_work')[0])->toBe(TaskStatus::Due);
    expect(check('stuck_work')[0])->toBe(TaskStatus::Overdue);
});

it('looks at open alerts, and a critical one is a failure', function () {
    expect(check('open_alerts')[0])->toBe(TaskStatus::Done);

    AlertEvent::factory()->resolved()->create();
    expect(check('open_alerts')[0])->toBe(TaskStatus::Done);

    AlertEvent::factory()->create();
    expect(check('open_alerts')[0])->toBe(TaskStatus::Due);

    AlertEvent::factory()->create(['severity' => AlertSeverity::Critical]);
    expect(check('open_alerts')[0])->toBe(TaskStatus::Overdue);
});

it('looks at Stripe events that failed and are still unresolved', function () {
    expect(check('stripe_webhooks')[0])->toBe(TaskStatus::Done);

    StripeWebhookFailure::record('evt_recent', 'invoice.paid', 'Timeout');
    expect(check('stripe_webhooks')[0])->toBe(TaskStatus::Due);

    StripeWebhookFailure::query()->update(['first_failed_at' => now()->subHours(30)]);
    expect(check('stripe_webhooks')[0])->toBe(TaskStatus::Overdue);

    StripeWebhookFailure::resolve('evt_recent');
    expect(check('stripe_webhooks')[0])->toBe(TaskStatus::Done);
});

it('reads how the last audit-log integrity check went', function () {
    expect(check('audit_chain')[0])->toBe(TaskStatus::Due);

    $run = ScheduledCommandRun::query()->create(['command' => 'super-admin:audit-verify', 'started_at' => now()->subHours(2), 'finished_at' => now()->subHours(2), 'exit_code' => 0]);
    expect(check('audit_chain')[0])->toBe(TaskStatus::Done);

    $run->update(['exit_code' => 1, 'error' => 'Chain broken at 42']);
    [$status, $message] = check('audit_chain');
    expect($status)->toBe(TaskStatus::Overdue)->and($message)->toContain('Chain broken at 42');

    $run->update(['exit_code' => 0, 'error' => null, 'started_at' => now()->subHours(40)]);
    expect(check('audit_chain')[0])->toBe(TaskStatus::Due);
});

it('warns while a read-only maintenance window is open', function () {
    expect(check('maintenance_window')[0])->toBe(TaskStatus::Done);

    Announcement::factory()->live()->maintenance()->create(['ends_at' => now()->addHour()]);

    [$status, $message] = check('maintenance_window');
    expect($status)->toBe(TaskStatus::Due)->and($message)->toContain('read-only');
});
