<?php

use App\Enums\RunStatus;
use App\Enums\SuperAdmin\SuperAdminRole;
use App\Models\Runs\Run;
use App\Models\SuperAdmin\ScheduledCommandRun;
use App\Models\SuperAdmin\SuperAdminAuditLog;
use App\Models\SuperAdmin\SystemHealthSnapshot;
use App\Models\User;
use App\Services\SuperAdmin\System\Checks\HealthCheck;
use App\Services\SuperAdmin\System\Checks\StorageCheck;
use App\Services\SuperAdmin\System\SchedulerMonitor;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->admin = superAdmin();
    $this->headers = superAdminHeaders($this->admin);
});

function failedJob(array $overrides = []): string
{
    $uuid = (string) Str::uuid();

    DB::table('failed_jobs')->insert([
        'uuid' => $uuid,
        'connection' => 'database',
        'queue' => 'default',
        'payload' => json_encode([
            'uuid' => $uuid,
            'displayName' => 'App\\Jobs\\SendInvoice',
            'attempts' => 0,
            'data' => ['commandName' => 'App\\Jobs\\SendInvoice', 'command' => serialize(new stdClass), 'api_key' => 'sk_live_abc', 'nested' => ['password' => 'hunter2']],
        ]),
        'exception' => "RuntimeException: boom password=hunter2 in /app/Jobs/SendInvoice.php:10\nStack trace:\n#0 {main}",
        'failed_at' => now(),
        ...$overrides,
    ]);

    return $uuid;
}

it('gives every role a read on system health but only operators and owners a write', function (SuperAdminRole $role, int $read, int $write) {
    $headers = superAdminHeaders(superAdmin($role));

    $this->withHeaders($headers)->getJson(saUrl('system/health'))->assertStatus($read);
    $this->withHeaders($headers)->postJson(saUrl('system/maintenance/workers/restart'), ['reason' => 'Deploying a fix to the queue'])->assertStatus($write);
})->with([
    'owner' => [SuperAdminRole::Owner, 200, 200],
    'operator' => [SuperAdminRole::Operator, 200, 200],
    'finance' => [SuperAdminRole::Finance, 200, 403],
    'support' => [SuperAdminRole::Support, 200, 403],
    'viewer' => [SuperAdminRole::Viewer, 200, 403],
]);

it('rolls every check into one status', function () {
    $response = $this->withHeaders($this->headers)->getJson(saUrl('system/health'))->assertOk();

    expect($response->json('data.checks'))->toHaveKeys(['database', 'cache', 'queues', 'scheduler', 'storage', 'disk', 'stuck_work'])
        ->and($response->json('data.checks.database.status'))->toBe('healthy')
        ->and($response->json('data.checks.database.latency_ms'))->toBeInt()
        ->and($response->json('data.status'))->toBeIn(['healthy', 'degraded', 'down']);
});

it('reports a check that throws as down without failing the others', function () {
    app()->bind(StorageCheck::class, fn () => new class implements HealthCheck
    {
        public function name(): string
        {
            return 'storage';
        }

        public function run(): array
        {
            throw new RuntimeException('disk exploded');
        }
    });

    $data = $this->withHeaders($this->headers)->getJson(saUrl('system/health'))->assertOk()->json('data');

    expect($data['status'])->toBe('down')
        ->and($data['checks']['storage'])->toMatchArray(['status' => 'down', 'message' => 'disk exploded'])
        ->and($data['checks']['database']['status'])->toBe('healthy');
});

it('lists the connectivity checks', function () {
    $this->withHeaders($this->headers)->getJson(saUrl('system/services'))
        ->assertOk()
        ->assertJsonStructure(['data' => ['database', 'cache', 'redis', 'reverb', 'mail', 'stripe', 'storage', 'disk']])
        ->assertJsonPath('data.redis.status', 'skipped');
});

it('reads queue sizes and waits from the database queue', function () {
    config(['queue.default' => 'database']);

    $row = fn (string $queue, array $overrides = []) => [
        'queue' => $queue, 'payload' => '{}', 'attempts' => 0, 'reserved_at' => null,
        'available_at' => now()->getTimestamp(), 'created_at' => now()->getTimestamp(), ...$overrides,
    ];

    DB::table('jobs')->insert([
        $row('default', ['available_at' => now()->subSeconds(1000)->getTimestamp()]),
        $row('default'),
        $row('default', ['reserved_at' => now()->getTimestamp()]),
        $row('default', ['available_at' => now()->addHour()->getTimestamp()]),
        $row('ai-agent'),
    ]);

    $queues = collect($this->withHeaders($this->headers)->getJson(saUrl('system/queues'))->assertOk()->json('data.queues'))->keyBy('name');

    expect($queues['default'])->toMatchArray(['pending' => 2, 'delayed' => 1, 'reserved' => 1, 'status' => 'down'])
        ->and($queues['default']['oldest_wait_seconds'])->toBeGreaterThanOrEqual(1000)
        ->and($queues['ai-agent'])->toMatchArray(['pending' => 1, 'status' => 'healthy']);

    $health = $this->withHeaders($this->headers)->getJson(saUrl('system/health'))->json('data.checks.queues');

    expect($health['status'])->toBe('down')->and($health['details']['slow_queues'])->toBe(['default']);
});

it('tells a dead scheduler from a live one by its heartbeat', function () {
    $status = fn () => $this->withHeaders($this->headers)->getJson(saUrl('system/health'))->json('data.checks.scheduler.status');

    expect($status())->toBe('degraded');

    $this->artisan('system:heartbeat')->assertSuccessful();
    expect($status())->toBe('healthy');

    Cache::forever(SchedulerMonitor::HEARTBEAT_KEY, now()->subMinutes(10)->getTimestamp());
    expect($status())->toBe('down');
});

it('lists scheduled commands with their expected frequency and last run', function () {
    $this->artisan('system:heartbeat');

    ScheduledCommandRun::query()->create(['command' => 'system:heartbeat', 'started_at' => now()->subMinute(), 'finished_at' => now()->subMinute(), 'exit_code' => 0, 'duration_ms' => 12]);
    ScheduledCommandRun::query()->create(['command' => 'triggers:run-due', 'started_at' => now()->subHour(), 'finished_at' => now()->subHour(), 'exit_code' => 0, 'duration_ms' => 12]);

    $commands = collect($this->withHeaders($this->headers)->getJson(saUrl('system/scheduler'))->assertOk()->json('data.commands'))->keyBy('command');

    expect($commands['system:heartbeat'])->toMatchArray(['expected_every_seconds' => 60, 'last_status' => 'ok', 'overdue' => false])
        // Due every minute, last started an hour ago.
        ->and($commands['triggers:run-due']['overdue'])->toBeTrue()
        ->and($commands['triggers:retry-stuck']['last_status'])->toBe('never_run');
});

it('records scheduled command runs from the scheduler events', function () {
    $task = app(Schedule::class)->command('inspire');

    Event::dispatch(new ScheduledTaskStarting($task));
    Event::dispatch(new ScheduledTaskFinished($task, 0.25));

    $run = ScheduledCommandRun::query()->where('command', 'inspire')->firstOrFail();

    expect($run->finished_at)->not->toBeNull()
        ->and($run->exit_code)->toBe(0)
        ->and($run->duration_ms)->toBe(250);
});

it('finds work that should have finished', function () {
    $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);

    $stuck = Run::factory()->forWorkspace($workspace)->create(['status' => RunStatus::Running, 'started_at' => now()->subHours(3)]);
    Run::factory()->forWorkspace($workspace)->create(['status' => RunStatus::Running, 'started_at' => now()->subMinutes(2)]);

    $data = $this->withHeaders($this->headers)->getJson(saUrl('system/stuck'))->assertOk()->json('data');

    expect($data['total'])->toBe(1)
        ->and($data['groups']['runs']['sample_ids'])->toBe([$stuck->id]);

    $this->withHeaders($this->headers)->getJson(saUrl('system/health'))->assertJsonPath('data.checks.stuck_work.status', 'degraded');
});

it('reports horizon as not applicable off a redis queue', function () {
    $this->withHeaders($this->headers)->getJson(saUrl('system/horizon'))
        ->assertOk()
        ->assertJsonPath('data.applicable', false);

    $this->withHeaders($this->headers)->postJson(saUrl('system/maintenance/horizon/pause'), ['reason' => 'Pausing for a migration'])
        ->assertStatus(409);
});

describe('failed jobs', function () {
    it('lists and filters them', function () {
        failedJob();
        failedJob(['queue' => 'ai-agent']);

        $this->withHeaders($this->headers)->getJson(saUrl('system/failed-jobs'))->assertOk()->assertJsonPath('meta.total', 2);
        $this->withHeaders($this->headers)->getJson(saUrl('system/failed-jobs?queue=ai-agent'))->assertJsonPath('meta.total', 1);
        $this->withHeaders($this->headers)->getJson(saUrl('system/failed-jobs?class=App\\Jobs\\SendInvoice'))->assertJsonPath('meta.total', 2);
        $this->withHeaders($this->headers)->getJson(saUrl('system/failed-jobs?class=Nope'))->assertJsonPath('meta.total', 0);
        $this->withHeaders($this->headers)->getJson(saUrl('system/failed-jobs?from='.now()->addDay()->toDateString()))->assertJsonPath('meta.total', 0);
    });

    it('shows a job with its secrets redacted and its serialized body left out', function () {
        $uuid = failedJob();

        $data = $this->withHeaders($this->headers)->getJson(saUrl("system/failed-jobs/{$uuid}"))->assertOk()->json('data');

        expect($data['job'])->toBe('App\\Jobs\\SendInvoice')
            ->and($data['payload']['data']['api_key'])->toBe('[redacted]')
            ->and($data['payload']['data']['nested']['password'])->toBe('[redacted]')
            ->and($data['payload']['data']['command'])->toBe('[serialized job omitted]')
            ->and($data['stack_trace'])->not->toContain('hunter2')
            ->and($data['stack_trace'])->toContain('RuntimeException');

        $this->withHeaders($this->headers)->getJson(saUrl('system/failed-jobs/'.Str::uuid()))->assertNotFound();
    });

    it('summarises the worst offenders', function () {
        failedJob();
        failedJob();

        $this->withHeaders($this->headers)->getJson(saUrl('system/failed-jobs/summary'))
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.by_class.0.class', 'App\\Jobs\\SendInvoice')
            ->assertJsonPath('data.by_class.0.failures', 2);
    });

    it('retries a job, with a reason, and audits it', function () {
        config(['queue.default' => 'database']);
        $uuid = failedJob();

        $this->withHeaders($this->headers)->postJson(saUrl("system/failed-jobs/{$uuid}/retry"))->assertUnprocessable();

        $this->withHeaders($this->headers)->postJson(saUrl("system/failed-jobs/{$uuid}/retry"), ['reason' => 'The invoice service is back up'])->assertOk();

        expect(DB::table('failed_jobs')->count())->toBe(0)
            ->and(DB::table('jobs')->count())->toBe(1);

        $log = SuperAdminAuditLog::query()->where('action', 'system.failed_job_retried')->firstOrFail();
        expect($log->reason)->toBe('The invoice service is back up');
    });

    it('retries every job matching a filter, up to the cap', function () {
        config(['queue.default' => 'database', 'super_admin_health.bulk_retry_limit' => 2]);
        failedJob();
        failedJob();
        failedJob();
        failedJob(['queue' => 'other']);

        $this->withHeaders($this->headers)
            ->postJson(saUrl('system/failed-jobs/retry'), ['reason' => 'Retrying the default queue', 'queue' => 'default'])
            ->assertOk()
            ->assertJsonPath('data.retried', 2)
            ->assertJsonPath('data.remaining', 1);

        expect(DB::table('failed_jobs')->count())->toBe(2);
    });

    it('needs an authenticator code to delete, and a filter to flush', function () {
        $uuid = failedJob();
        failedJob(['queue' => 'other']);

        $this->withHeaders($this->headers)->deleteJson(saUrl("system/failed-jobs/{$uuid}"), ['reason' => 'Garbage from a bad deploy'])->assertUnprocessable();
        $this->withHeaders($this->headers)->deleteJson(saUrl("system/failed-jobs/{$uuid}"), stepUp($this->admin))->assertOk();
        expect(DB::table('failed_jobs')->count())->toBe(1);

        $this->withHeaders($this->headers)->postJson(saUrl('system/failed-jobs/flush'), stepUp($this->admin))->assertUnprocessable()->assertJsonValidationErrors('everything');
        expect(DB::table('failed_jobs')->count())->toBe(1);

        $this->withHeaders($this->headers)->postJson(saUrl('system/failed-jobs/flush'), stepUp($this->admin, ['queue' => 'other']))->assertOk()->assertJsonPath('data.deleted', 1);
        expect(DB::table('failed_jobs')->count())->toBe(0);
    });
});

describe('history', function () {
    it('captures a snapshot and prunes old ones', function () {
        SystemHealthSnapshot::query()->create(['status' => 'healthy', 'checks' => [], 'created_at' => now()->subDays(45)]);

        $this->artisan('system:health-snapshot')->assertSuccessful();

        expect(SystemHealthSnapshot::query()->count())->toBe(1);
    });

    it('works out uptime and incidents from the snapshots', function () {
        $snapshot = fn (string $status, $at, array $checks = []) => SystemHealthSnapshot::query()->create(['status' => $status, 'checks' => $checks, 'created_at' => $at]);

        $snapshot('healthy', now()->subMinutes(40));
        $snapshot('down', now()->subMinutes(30), ['queues' => ['status' => 'down', 'message' => 'x']]);
        $snapshot('degraded', now()->subMinutes(20), ['queues' => ['status' => 'degraded', 'message' => 'x']]);
        $snapshot('healthy', now()->subMinutes(10));

        $data = $this->withHeaders($this->headers)->getJson(saUrl('system/history'))->assertOk()->json('data');

        expect($data['uptime']['24h'])->toMatchArray(['snapshots' => 4, 'healthy_percent' => 50.0, 'availability_percent' => 75.0])
            ->and($data['incidents'])->toHaveCount(1)
            ->and($data['incidents'][0])->toMatchArray(['worst_status' => 'down', 'checks' => ['queues']])
            ->and($data['incidents'][0]['ended_at'])->not->toBeNull()
            ->and($data['timeline'])->toHaveCount(4);
    });
});
