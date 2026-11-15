<?php

use App\Enums\Changelog\ChangelogStatus;
use App\Models\Changelog\ChangelogEntry;
use App\Models\SuperAdmin\SuperAdminAuditLog;

it('publishes scheduled entries whose time has come and leaves the rest', function () {
    $due = ChangelogEntry::factory()->due()->create();
    $later = ChangelogEntry::factory()->scheduled()->create();
    $draft = ChangelogEntry::factory()->create();

    $scheduledFor = $due->scheduled_for;

    $this->artisan('changelog:publish-due')->assertSuccessful();

    $due->refresh();

    expect($due->status)->toBe(ChangelogStatus::Published);
    expect($due->published_at->equalTo($scheduledFor))->toBeTrue();
    expect($due->scheduled_for)->toBeNull();
    expect($later->fresh()->status)->toBe(ChangelogStatus::Scheduled);
    expect($draft->fresh()->status)->toBe(ChangelogStatus::Draft);
    expect(SuperAdminAuditLog::query()->where('action', 'changelog.published')->where('subject_id', $due->id)->exists())->toBeTrue();
});

it('makes a due entry visible to readers once it runs', function () {
    ChangelogEntry::factory()->due()->create(['slug' => 'going-out']);

    $this->getJson('/api/v1/public/changelog/going-out')->assertNotFound();

    $this->artisan('changelog:publish-due')->assertSuccessful();

    $this->getJson('/api/v1/public/changelog/going-out')->assertOk();
});

it('does nothing when nothing is due', function () {
    $this->artisan('changelog:publish-due')->expectsOutputToContain('Published 0 changelog entries.')->assertSuccessful();
});
