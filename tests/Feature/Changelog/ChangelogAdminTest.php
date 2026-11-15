<?php

use App\Enums\Changelog\ChangelogStatus;
use App\Enums\SuperAdmin\SuperAdminRole;
use App\Models\Changelog\ChangelogEntry;
use App\Models\SuperAdmin\SuperAdminAuditLog;

beforeEach(function () {
    $this->admin = superAdmin(SuperAdminRole::Operator);
    $this->headers = superAdminHeaders($this->admin);
});

it('saves an entry as an unpublished draft with a generated slug, and audits it', function () {
    $this->withHeaders($this->headers)
        ->postJson(saUrl('changelog/entries'), [
            'title' => 'Workflow folders are here',
            'summary' => 'Organise workflows into folders.',
            'body' => "### Added\n\n- Folders",
            'category' => 'feature',
            'areas' => ['workflows', 'workflows'],
            'version' => '2.4.0',
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.slug', 'workflow-folders-are-here')
        ->assertJsonPath('data.visibility', 'public')
        ->assertJsonPath('data.areas', ['workflows'])
        ->assertJsonPath('data.published_at', null);

    $entry = ChangelogEntry::query()->firstOrFail();

    expect($entry->created_by)->toBe($this->admin->id);
    expect(SuperAdminAuditLog::query()->where('action', 'changelog.created')->where('subject_id', $entry->id)->exists())->toBeTrue();
});

it('keeps slugs unique and refuses to change one afterwards', function () {
    ChangelogEntry::factory()->create(['slug' => 'big-news']);

    $this->withHeaders($this->headers)
        ->postJson(saUrl('changelog/entries'), ['title' => 'Big news', 'summary' => 'x', 'body' => 'x'])
        ->assertCreated()
        ->assertJsonPath('data.slug', 'big-news-2');

    $this->withHeaders($this->headers)
        ->postJson(saUrl('changelog/entries'), ['title' => 'Other', 'slug' => 'big-news', 'summary' => 'x', 'body' => 'x'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('slug');

    $entry = ChangelogEntry::factory()->create();

    $this->withHeaders($this->headers)
        ->patchJson(saUrl("changelog/entries/{$entry->id}"), ['slug' => 'moved'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('slug');
});

it('rejects unsafe cover images, bad areas and missing fields', function () {
    $this->withHeaders($this->headers)
        ->postJson(saUrl('changelog/entries'), ['title' => 'x'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['summary', 'body']);

    $this->withHeaders($this->headers)
        ->postJson(saUrl('changelog/entries'), [
            'title' => 'x', 'summary' => 'x', 'body' => 'x',
            'cover_image_url' => 'javascript:alert(1)',
            'areas' => ['Not Valid'],
            'category' => 'nonsense',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['cover_image_url', 'areas.0', 'category']);
});

it('needs an audience for an audience entry and drops it for other visibilities', function () {
    $this->withHeaders($this->headers)
        ->postJson(saUrl('changelog/entries'), ['title' => 'Pro only', 'summary' => 'x', 'body' => 'x', 'visibility' => 'audience'])
        ->assertUnprocessable();

    $this->withHeaders($this->headers)
        ->postJson(saUrl('changelog/entries'), ['title' => 'Everyone', 'summary' => 'x', 'body' => 'x', 'visibility' => 'authenticated', 'audience' => ['inactive_days' => 30]])
        ->assertCreated()
        ->assertJsonPath('data.audience', null);

    $this->withHeaders($this->headers)
        ->postJson(saUrl('changelog/entries'), ['title' => 'Dormant', 'summary' => 'x', 'body' => 'x', 'visibility' => 'audience', 'audience' => ['inactive_days' => 30]])
        ->assertCreated()
        ->assertJsonPath('data.audience.inactive_days', 30);
});

it('publishes, takes down and archives an entry, auditing each step', function () {
    $entry = ChangelogEntry::factory()->create();

    $this->withHeaders($this->headers)->postJson(saUrl("changelog/entries/{$entry->id}/publish"))
        ->assertOk()
        ->assertJsonPath('data.status', 'published');

    expect($entry->fresh()->published_at)->not->toBeNull();

    $this->withHeaders($this->headers)->postJson(saUrl("changelog/entries/{$entry->id}/unpublish"))
        ->assertOk()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.published_at', null);

    $this->withHeaders($this->headers)->postJson(saUrl("changelog/entries/{$entry->id}/archive"))
        ->assertOk()
        ->assertJsonPath('data.status', 'archived');

    foreach (['published', 'unpublished', 'archived'] as $action) {
        expect(SuperAdminAuditLog::query()->where('action', "changelog.{$action}")->where('subject_id', $entry->id)->exists())->toBeTrue();
    }
});

it('schedules an entry for the future only', function () {
    $entry = ChangelogEntry::factory()->create();

    $this->withHeaders($this->headers)
        ->postJson(saUrl("changelog/entries/{$entry->id}/schedule"), ['scheduled_for' => now()->subHour()->toIso8601String()])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('scheduled_for');

    $this->withHeaders($this->headers)
        ->postJson(saUrl("changelog/entries/{$entry->id}/schedule"), ['scheduled_for' => now()->addDay()->toIso8601String()])
        ->assertOk()
        ->assertJsonPath('data.status', 'scheduled');

    expect($entry->fresh()->status)->toBe(ChangelogStatus::Scheduled);
});

it('will not schedule an entry that is already live', function () {
    $entry = ChangelogEntry::factory()->published()->create();

    $this->withHeaders($this->headers)
        ->postJson(saUrl("changelog/entries/{$entry->id}/schedule"), ['scheduled_for' => now()->addDay()->toIso8601String()])
        ->assertStatus(409);
});

it('lists entries with filters and read counts, and shows one', function () {
    ChangelogEntry::factory()->published()->create(['title' => 'Alpha launch', 'category' => 'feature']);
    ChangelogEntry::factory()->create(['title' => 'Beta fix', 'category' => 'fix']);

    $this->withHeaders($this->headers)->getJson(saUrl('changelog/entries'))
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $this->withHeaders($this->headers)->getJson(saUrl('changelog/entries?status=draft'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Beta fix');

    $this->withHeaders($this->headers)->getJson(saUrl('changelog/entries?q=alpha'))
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $entry = ChangelogEntry::query()->firstOrFail();

    $this->withHeaders($this->headers)->getJson(saUrl("changelog/entries/{$entry->id}"))
        ->assertOk()
        ->assertJsonPath('data.reads_count', 0);
});

it('updates and deletes an entry', function () {
    $entry = ChangelogEntry::factory()->published()->create(['title' => 'Old']);

    $this->withHeaders($this->headers)
        ->patchJson(saUrl("changelog/entries/{$entry->id}"), ['title' => 'New', 'is_major' => true])
        ->assertOk()
        ->assertJsonPath('data.title', 'New')
        ->assertJsonPath('data.is_major', true);

    expect($entry->fresh()->updated_by)->toBe($this->admin->id);

    $this->withHeaders($this->headers)->deleteJson(saUrl("changelog/entries/{$entry->id}"))->assertOk();

    expect(ChangelogEntry::query()->count())->toBe(0);
    expect(SuperAdminAuditLog::query()->where('action', 'changelog.deleted')->exists())->toBeTrue();
});

it('previews markdown as safe html', function () {
    $response = $this->withHeaders($this->headers)
        ->postJson(saUrl('changelog/preview'), ['body' => "**bold** <script>alert(1)</script>\n\n[bad](javascript:alert(1)) [good](https://example.test)"])
        ->assertOk();

    $html = $response->json('data.html');

    expect($html)->toContain('<strong>bold</strong>')
        ->not->toContain('<script>')
        ->not->toContain('javascript:')
        ->toContain('https://example.test');
});

it('lets every role read but only operators and owners write', function (SuperAdminRole $role, int $read, int $write) {
    $headers = superAdminHeaders(superAdmin($role));

    $this->withHeaders($headers)->getJson(saUrl('changelog/entries'))->assertStatus($read);
    $this->withHeaders($headers)
        ->postJson(saUrl('changelog/entries'), ['title' => 'x', 'summary' => 'x', 'body' => 'x'])
        ->assertStatus($write);
})->with([
    'owner' => [SuperAdminRole::Owner, 200, 201],
    'operator' => [SuperAdminRole::Operator, 200, 201],
    'finance' => [SuperAdminRole::Finance, 200, 403],
    'support' => [SuperAdminRole::Support, 200, 403],
    'viewer' => [SuperAdminRole::Viewer, 200, 403],
]);

it('needs a super admin token', function () {
    $this->getJson(saUrl('changelog/entries'))->assertUnauthorized();
});
