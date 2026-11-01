<?php

use App\Enums\SuperAdmin\SuperAdminRole;
use App\Events\Broadcasts\AnnouncementsChanged;
use App\Models\Billing\Plan;
use App\Models\Broadcasts\Announcement;
use App\Models\SuperAdmin\SuperAdminAuditLog;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->admin = superAdmin(SuperAdminRole::Operator);
    $this->headers = superAdminHeaders($this->admin);
});

it('saves an announcement as an unpublished draft and audits it', function () {
    $this->withHeaders($this->headers)
        ->postJson(saUrl('broadcasts/announcements'), [
            'title' => 'New: workflow folders',
            'body' => 'Organise your workflows.',
            'severity' => 'info',
            'cta_label' => 'See what is new',
            'cta_url' => '/changelog',
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.published_at', null)
        ->assertJsonPath('data.audience.roles', 'all');

    $announcement = Announcement::query()->firstOrFail();

    expect($announcement->created_by)->toBe($this->admin->id);
    expect(SuperAdminAuditLog::query()->where('action', 'announcements.created')->where('subject_id', $announcement->id)->exists())->toBeTrue();
});

it('rejects unsafe links, a missing label and a backwards window', function () {
    $this->withHeaders($this->headers)
        ->postJson(saUrl('broadcasts/announcements'), [
            'title' => 'Bad',
            'cta_label' => 'Click',
            'cta_url' => 'javascript:alert(1)',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('cta_url');

    $this->withHeaders($this->headers)
        ->postJson(saUrl('broadcasts/announcements'), ['title' => 'Bad', 'cta_url' => 'https://example.test'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('cta_label');

    $this->withHeaders($this->headers)
        ->postJson(saUrl('broadcasts/announcements'), ['title' => 'Bad', 'starts_at' => now()->addDay()->toIso8601String(), 'ends_at' => now()->toIso8601String()])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('ends_at');

    $this->withHeaders($this->headers)
        ->postJson(saUrl('broadcasts/announcements'), ['title' => 'Bad', 'audience' => ['plans' => ['no-such-plan']]])
        ->assertUnprocessable();

    $this->withHeaders($this->headers)
        ->postJson(saUrl('broadcasts/announcements'), ['title' => 'Bad', 'cta_label' => 'Go', 'cta_url' => '//evil.test'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('cta_url');
});

it('needs a full window for maintenance, and keeps a write-blocking one short', function () {
    $this->withHeaders($this->headers)
        ->postJson(saUrl('broadcasts/announcements'), ['title' => 'Upgrade', 'type' => 'maintenance'])
        ->assertUnprocessable();

    $this->withHeaders($this->headers)
        ->postJson(saUrl('broadcasts/announcements'), [
            'title' => 'Upgrade',
            'type' => 'maintenance',
            'blocks_writes' => true,
            'starts_at' => now()->addHour()->toIso8601String(),
            'ends_at' => now()->addDays(3)->toIso8601String(),
        ])
        ->assertUnprocessable();

    $this->withHeaders($this->headers)
        ->postJson(saUrl('broadcasts/announcements'), [
            'title' => 'Upgrade',
            'type' => 'maintenance',
            'blocks_writes' => true,
            'starts_at' => now()->addHour()->toIso8601String(),
            'ends_at' => now()->addHours(3)->toIso8601String(),
        ])
        ->assertCreated()
        ->assertJsonPath('data.blocks_writes', true);
});

it('never lets a plain banner block writes', function () {
    $this->withHeaders($this->headers)
        ->postJson(saUrl('broadcasts/announcements'), ['title' => 'Hi', 'blocks_writes' => true])
        ->assertCreated()
        ->assertJsonPath('data.blocks_writes', false);
});

it('publishes and takes down, and tells open apps to refetch', function () {
    Event::fake([AnnouncementsChanged::class]);

    $announcement = Announcement::factory()->create();

    $this->withHeaders($this->headers)
        ->postJson(saUrl("broadcasts/announcements/{$announcement->id}/publish"))
        ->assertOk()
        ->assertJsonPath('data.status', 'live');

    Event::assertDispatched(AnnouncementsChanged::class, fn (AnnouncementsChanged $event) => $event->reason === 'published');

    $this->withHeaders($this->headers)
        ->postJson(saUrl("broadcasts/announcements/{$announcement->id}/unpublish"))
        ->assertOk()
        ->assertJsonPath('data.status', 'draft');

    Event::assertDispatched(AnnouncementsChanged::class, fn (AnnouncementsChanged $event) => $event->reason === 'unpublished');

    expect(SuperAdminAuditLog::query()->whereIn('action', ['announcements.published', 'announcements.unpublished'])->count())->toBe(2);
});

it('will not publish an announcement that has already ended', function () {
    $announcement = Announcement::factory()->create(['starts_at' => now()->subDays(2), 'ends_at' => now()->subDay()]);

    $this->withHeaders($this->headers)
        ->postJson(saUrl("broadcasts/announcements/{$announcement->id}/publish"))
        ->assertStatus(409);
});

it('updates, audits only what changed, and deletes', function () {
    $announcement = Announcement::factory()->create(['title' => 'Before']);

    $this->withHeaders($this->headers)
        ->patchJson(saUrl("broadcasts/announcements/{$announcement->id}"), ['title' => 'After'])
        ->assertOk()
        ->assertJsonPath('data.title', 'After');

    $log = SuperAdminAuditLog::query()->where('action', 'announcements.updated')->firstOrFail();

    expect($log->before)->toBe(['title' => 'Before'])->and($log->after)->toBe(['title' => 'After']);

    $this->withHeaders($this->headers)->deleteJson(saUrl("broadcasts/announcements/{$announcement->id}"))->assertOk();

    expect(Announcement::query()->count())->toBe(0);
});

it('filters the list by status and type', function () {
    Announcement::factory()->create(['title' => 'draft']);
    Announcement::factory()->live()->create(['title' => 'live']);
    Announcement::factory()->scheduled()->create(['title' => 'scheduled']);
    Announcement::factory()->ended()->create(['title' => 'ended']);
    Announcement::factory()->maintenance()->create(['title' => 'maintenance']);

    foreach (['draft', 'scheduled', 'ended'] as $status) {
        $this->withHeaders($this->headers)->getJson(saUrl("broadcasts/announcements?status={$status}"))
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', $status);
    }

    $this->withHeaders($this->headers)->getJson(saUrl('broadcasts/announcements?status=live'))->assertJsonCount(2, 'data');
    $this->withHeaders($this->headers)->getJson(saUrl('broadcasts/announcements?status=live&type=maintenance'))->assertJsonCount(1, 'data');
    $this->withHeaders($this->headers)->getJson(saUrl('broadcasts/announcements?type=maintenance'))->assertJsonCount(1, 'data');
    $this->withHeaders($this->headers)->getJson(saUrl('broadcasts/announcements?status=nonsense'))->assertUnprocessable();
});

it('previews an audience of plans without storing anything', function () {
    Plan::factory()->create(['slug' => 'free']);
    config(['billing.default_plan' => 'free']);
    referralUser();

    $this->withHeaders($this->headers)
        ->postJson(saUrl('broadcasts/audience/preview'), ['audience' => ['plans' => ['free']]])
        ->assertOk()
        ->assertJsonPath('data.recipient_count', 1)
        ->assertJsonCount(1, 'data.sample');
});
