<?php

use App\Enums\Account\DataExportStatus;
use App\Jobs\Account\BuildDataExportJob;
use App\Models\Account\DataExport;
use App\Models\User;
use App\Notifications\Account\DataExportReadyNotification;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

it('queues a data export on request', function () {
    Queue::fake();
    $user = User::factory()->create();

    $this->actingAs($user, 'api')
        ->postJson('/api/v1/user/data-exports')
        ->assertStatus(202)
        ->assertJsonPath('data.export.status', 'pending');

    Queue::assertPushed(BuildDataExportJob::class);
});

it('returns the export already in flight instead of starting another', function () {
    Queue::fake();
    $user = User::factory()->create();
    $pending = DataExport::factory()->for($user)->create();

    $this->actingAs($user, 'api')
        ->postJson('/api/v1/user/data-exports')
        ->assertJsonPath('data.export.id', $pending->id);

    Queue::assertNothingPushed();
    expect($user->dataExports()->count())->toBe(1);
});

it('builds an archive of the account data and emails the owner', function () {
    Storage::fake(config('filesystems.default'));
    Notification::fake();
    $user = User::factory()->create(['name' => 'Jane Doe']);
    app(WorkspaceService::class)->create($user, ['name' => 'Acme']);
    $export = DataExport::factory()->for($user)->create();

    (new BuildDataExportJob($export->id))->handle(app(App\Services\Account\DataExportService::class));

    $export->refresh();
    expect($export->status)->toBe(DataExportStatus::Completed)
        ->and($export->isDownloadable())->toBeTrue();

    $zipPath = tempnam(sys_get_temp_dir(), 'test-export-');
    file_put_contents($zipPath, Storage::disk($export->disk)->get($export->path));
    $zip = new ZipArchive;
    $zip->open($zipPath);

    $profile = json_decode($zip->getFromName('profile.json'), true);
    $workspaces = json_decode($zip->getFromName('workspaces.json'), true);
    $zip->close();
    unlink($zipPath);

    expect($profile['name'])->toBe('Jane Doe')
        ->and($profile)->not->toHaveKey('password')
        ->and($workspaces[0]['workspace_name'])->toBe('Acme')
        ->and($workspaces[0]['is_owner'])->toBeTrue();

    Notification::assertSentTo($user, DataExportReadyNotification::class);
});

it('downloads a finished export', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $export = DataExport::factory()->completed()->for($user)->create();
    Storage::disk('local')->put($export->path, 'zip-bytes');

    $this->actingAs($user, 'api')
        ->get("/api/v1/user/data-exports/{$export->id}/download")
        ->assertOk()
        ->assertDownload($export->fileName());
});

it('does not let one account download another account export', function () {
    Storage::fake('local');
    $export = DataExport::factory()->completed()->create();
    Storage::disk('local')->put($export->path, 'zip-bytes');

    $this->actingAs(User::factory()->create(), 'api')
        ->getJson("/api/v1/user/data-exports/{$export->id}/download")
        ->assertNotFound();
});

it('refuses to download an expired export', function () {
    $user = User::factory()->create();
    $export = DataExport::factory()->expired()->for($user)->create();

    $this->actingAs($user, 'api')
        ->getJson("/api/v1/user/data-exports/{$export->id}/download")
        ->assertStatus(410);
});

it('prunes archives past their download window', function () {
    Storage::fake('local');
    $expired = DataExport::factory()->expired()->create();
    $current = DataExport::factory()->completed()->create();
    Storage::disk('local')->put($expired->path, 'old');
    Storage::disk('local')->put($current->path, 'new');

    $this->artisan('account:prune-data-exports')->assertSuccessful();

    Storage::disk('local')->assertMissing($expired->path);
    Storage::disk('local')->assertExists($current->path);
    expect($expired->fresh()->status)->toBe(DataExportStatus::Expired)
        ->and($current->fresh()->status)->toBe(DataExportStatus::Completed);
});
