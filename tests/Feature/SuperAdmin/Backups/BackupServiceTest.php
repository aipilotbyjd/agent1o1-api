<?php

use App\Enums\System\BackupStatus;
use App\Enums\System\BackupType;
use App\Jobs\System\RunBackupJob;
use App\Models\System\Backup;
use App\Services\System\Backups\BackupService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config([
        'backups.enabled' => true,
        'backups.disk' => 'backups',
        'backup.backup.name' => 'agent1o1',
    ]);

    Storage::fake('backups');
});

/**
 * A backup service whose dump is faked: it writes the file `backup:run`
 * would, or fails, so no database dump is needed.
 */
function backupServiceWhere(Closure $dump): BackupService
{
    $service = Mockery::mock(BackupService::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $service->shouldReceive('runDump')->andReturnUsing($dump);

    return $service;
}

function writesBackupFile(): Closure
{
    return fn (Backup $backup) => Storage::disk($backup->disk)->put('agent1o1/'.$backup->id.'.zip', 'zip-bytes');
}

it('runs a backup, finds the file and records its size', function () {
    $backup = Backup::factory()->pending()->manual()->create(['disk' => 'backups']);

    $result = backupServiceWhere(writesBackupFile())->execute($backup);

    expect($result->status)->toBe(BackupStatus::Completed)
        ->and($result->path)->toBe("agent1o1/{$backup->id}.zip")
        ->and($result->size_bytes)->toBe(strlen('zip-bytes'))
        ->and($result->started_at)->not->toBeNull()
        ->and($result->finished_at)->not->toBeNull();
    Storage::disk('backups')->assertExists("agent1o1/{$backup->id}.zip");
});

it('records the error when the dump fails', function () {
    $backup = Backup::factory()->pending()->create(['disk' => 'backups']);

    $result = backupServiceWhere(fn () => throw new RuntimeException('pg_dump: connection refused'))->execute($backup);

    expect($result->status)->toBe(BackupStatus::Failed)
        ->and($result->error)->toContain('pg_dump: connection refused')
        ->and($result->path)->toBeNull();
});

it('fails a run whose file never reached the disk', function () {
    $backup = Backup::factory()->pending()->create(['disk' => 'backups']);

    $result = backupServiceWhere(fn () => null)->execute($backup);

    expect($result->status)->toBe(BackupStatus::Failed)->and($result->error)->toContain('is not on the backups disk');
});

it('leaves a backup that is not pending alone', function () {
    $backup = Backup::factory()->create();

    backupServiceWhere(writesBackupFile())->execute($backup);

    Storage::disk('backups')->assertMissing("agent1o1/{$backup->id}.zip");
});

it('marks runs whose worker died as failed so they stop blocking new backups', function () {
    Bus::fake();
    config(['backups.stale_after_minutes' => 120]);

    $stuck = Backup::factory()->running()->create(['created_at' => now()->subHours(3)]);

    expect(app(BackupService::class)->queue(BackupType::Scheduled))->not->toBeNull();
    expect($stuck->fresh()->status)->toBe(BackupStatus::Failed);
    Bus::assertDispatched(RunBackupJob::class);
});

it('queues a scheduled backup from the command, and skips when one is active', function () {
    Bus::fake();

    $this->artisan('system:backup')->assertSuccessful();
    $this->artisan('system:backup')->assertSuccessful();

    expect(Backup::query()->count())->toBe(1)->and(Backup::query()->sole()->type)->toBe(BackupType::Scheduled);
    Bus::assertDispatchedTimes(RunBackupJob::class, 1);
});

it('does nothing from the command while backups are disabled', function () {
    Bus::fake();
    config(['backups.enabled' => false]);

    $this->artisan('system:backup')->assertSuccessful();

    expect(Backup::query()->count())->toBe(0);
});

it('schedules backups and the monitor, and never the cleanup', function () {
    $commands = collect(app(Schedule::class)->events())->map(fn ($event): string => (string) $event->command);

    expect($commands->contains(fn (string $command): bool => str_contains($command, 'system:backup')))->toBeTrue()
        ->and($commands->contains(fn (string $command): bool => str_contains($command, 'backup:monitor')))->toBeTrue()
        ->and($commands->contains(fn (string $command): bool => str_contains($command, 'backup:clean')))->toBeFalse();
});
