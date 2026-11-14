<?php

use App\Enums\System\BackupStatus;
use App\Enums\System\BackupType;
use App\Enums\SuperAdmin\SuperAdminRole;
use App\Jobs\System\RunBackupJob;
use App\Models\SuperAdmin\SuperAdminAuditLog;
use App\Models\System\Backup;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config(['backups.enabled' => true, 'backups.disk' => 'backups']);

    $this->admin = superAdmin(SuperAdminRole::Owner);
    $this->headers = superAdminHeaders($this->admin);
});

it('lets every role read backups, operators start one, and only owners download', function (SuperAdminRole $role, int $read, int $start, int $download) {
    Bus::fake();
    Storage::fake('backups')->buildTemporaryUrlsUsing(fn (string $path): string => "https://backups.test/{$path}");

    $admin = superAdmin($role);
    $headers = superAdminHeaders($admin);
    $backup = Backup::factory()->create();

    $this->withHeaders($headers)->getJson(saUrl('backups'))->assertStatus($read);
    $this->withHeaders($headers)->getJson(saUrl('backups/summary'))->assertStatus($read);
    $this->withHeaders($headers)->getJson(saUrl("backups/{$backup->id}"))->assertStatus($read);
    $this->withHeaders($headers)->postJson(saUrl('backups'))->assertStatus($start);
    $this->withHeaders($headers)->postJson(saUrl("backups/{$backup->id}/download-link"), stepUp($admin))->assertStatus($download);
})->with([
    'owner' => [SuperAdminRole::Owner, 200, 202, 200],
    'operator' => [SuperAdminRole::Operator, 200, 202, 403],
    'finance' => [SuperAdminRole::Finance, 200, 403, 403],
    'support' => [SuperAdminRole::Support, 200, 403, 403],
    'viewer' => [SuperAdminRole::Viewer, 200, 403, 403],
]);

it('lists backups newest first and filters them', function () {
    $old = Backup::factory()->create(['created_at' => now()->subDay()]);
    $failed = Backup::factory()->failed()->manual()->create();

    $this->withHeaders($this->headers)->getJson(saUrl('backups'))
        ->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('data.0.id', $failed->id);

    $this->withHeaders($this->headers)->getJson(saUrl('backups?status=failed'))->assertJsonPath('meta.total', 1);
    $this->withHeaders($this->headers)->getJson(saUrl('backups?type=scheduled'))->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $old->id);
    $this->withHeaders($this->headers)->getJson(saUrl('backups?status=bogus'))->assertUnprocessable();
    $this->withHeaders($this->headers)->getJson(saUrl("backups/{$failed->id}"))->assertOk()->assertJsonPath('data.backup.error', 'pg_dump: connection refused');
});

it('summarises whether the schedule is producing backups', function () {
    $this->withHeaders($this->headers)->getJson(saUrl('backups/summary'))
        ->assertOk()
        ->assertJsonPath('data.healthy', false)
        ->assertJsonPath('data.last_completed', null);

    Backup::factory()->create(['size_bytes' => 1000]);
    Backup::factory()->create(['size_bytes' => 500]);

    $this->withHeaders($this->headers)->getJson(saUrl('backups/summary'))
        ->assertJsonPath('data.healthy', true)
        ->assertJsonPath('data.completed_count', 2)
        ->assertJsonPath('data.total_size_bytes', 1500);

    Backup::query()->update(['finished_at' => now()->subHours(30)]);
    $this->withHeaders($this->headers)->getJson(saUrl('backups/summary'))->assertJsonPath('data.healthy', false);
});

it('queues a manual backup, audits it, and refuses a second while one is active', function () {
    Bus::fake();

    $this->withHeaders($this->headers)->postJson(saUrl('backups'))
        ->assertStatus(202)
        ->assertJsonPath('data.backup.status', 'pending')
        ->assertJsonPath('data.backup.type', 'manual');

    $backup = Backup::query()->sole();
    expect($backup->triggered_by)->toBe($this->admin->id);
    Bus::assertDispatched(RunBackupJob::class, fn (RunBackupJob $job): bool => $job->backupId === $backup->id);
    expect(SuperAdminAuditLog::query()->where('action', 'backups.requested')->count())->toBe(1);

    $this->withHeaders($this->headers)->postJson(saUrl('backups'))->assertStatus(409);
    expect(Backup::query()->count())->toBe(1);
});

it('refuses to start a backup when backups are disabled', function () {
    Bus::fake();
    config(['backups.enabled' => false]);

    $this->withHeaders($this->headers)->postJson(saUrl('backups'))->assertStatus(409);

    Bus::assertNotDispatched(RunBackupJob::class);
});

it('issues a short-lived download link only with a reason and a fresh code', function () {
    Storage::fake('backups')->buildTemporaryUrlsUsing(fn (string $path): string => "https://backups.test/{$path}?signed=1");
    $backup = Backup::factory()->create();

    $this->withHeaders($this->headers)->postJson(saUrl("backups/{$backup->id}/download-link"))->assertUnprocessable();
    $this->withHeaders($this->headers)->postJson(saUrl("backups/{$backup->id}/download-link"), ['reason' => 'Verified request from the account owner', 'totp_code' => '000000'])->assertUnprocessable();

    $this->withHeaders($this->headers)->postJson(saUrl("backups/{$backup->id}/download-link"), stepUp($this->admin))
        ->assertOk()
        ->assertJsonPath('data.url', "https://backups.test/{$backup->path}?signed=1");

    $audit = SuperAdminAuditLog::query()->where('action', 'backups.download-link-issued')->sole();
    expect($audit->reason)->toBe('Verified request from the account owner');
});

it('has no download link for a backup that has no file', function () {
    Storage::fake('backups')->buildTemporaryUrlsUsing(fn (string $path): string => "https://backups.test/{$path}");
    $failed = Backup::factory()->failed()->create();

    $this->withHeaders($this->headers)->postJson(saUrl("backups/{$failed->id}/download-link"), stepUp($this->admin))->assertStatus(409);
});

it('offers no way to change or delete a backup', function () {
    $backupRoutes = collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn ($route): bool => str_starts_with($route->getName() ?? '', 'super-admin.backups.'));

    expect($backupRoutes)->not->toBeEmpty();

    $methods = $backupRoutes->flatMap(fn ($route): array => $route->methods())->unique()->values()->all();

    expect($methods)->not->toContain('DELETE', 'PUT', 'PATCH');
});

it('refuses to delete or edit a finished backup in code', function () {
    $backup = Backup::factory()->create();

    expect(fn () => $backup->delete())->toThrow(LogicException::class);
    expect(fn () => $backup->update(['path' => 'elsewhere.zip']))->toThrow(LogicException::class);
    expect(fn () => Backup::factory()->failed()->create()->update(['status' => BackupStatus::Completed]))->toThrow(LogicException::class);

    expect($backup->fresh()->path)->not->toBe('elsewhere.zip');
    expect(Backup::query()->count())->toBeGreaterThanOrEqual(1);
});

it('allows an unfinished backup to move forward', function () {
    $backup = Backup::factory()->running()->create(['type' => BackupType::Manual]);

    $backup->update(['status' => BackupStatus::Completed, 'finished_at' => now()]);

    expect($backup->fresh()->status)->toBe(BackupStatus::Completed);
});
