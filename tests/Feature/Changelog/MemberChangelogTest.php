<?php

use App\Enums\Changelog\ChangelogVisibility;
use App\Models\Billing\Plan;
use App\Models\Changelog\ChangelogEntry;
use App\Models\Changelog\ChangelogRead;
use App\Models\User;
use Laravel\Passport\Passport;

it('needs a signed-in member', function () {
    $this->getJson('/api/v1/changelog')->assertUnauthorized();
    $this->getJson('/api/v1/changelog/unread-count')->assertUnauthorized();
    $this->postJson('/api/v1/changelog/read-all')->assertUnauthorized();
});

it('shows public and members-only entries but never unreleased ones', function () {
    ChangelogEntry::factory()->published()->create(['title' => 'Public']);
    ChangelogEntry::factory()->published()->visibleTo(ChangelogVisibility::Authenticated)->create(['title' => 'Members']);
    ChangelogEntry::factory()->create(['title' => 'Draft']);
    ChangelogEntry::factory()->scheduled()->create(['title' => 'Scheduled']);
    ChangelogEntry::factory()->archived()->create(['title' => 'Archived']);

    Passport::actingAs(User::factory()->create());

    $this->getJson('/api/v1/changelog')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.is_read', false);
});

it('shows an audience entry only to members it is aimed at', function () {
    $pro = Plan::factory()->create(['slug' => 'pro-test']);

    ChangelogEntry::factory()->published()->visibleTo(ChangelogVisibility::Audience, ['plans' => [$pro->slug], 'roles' => 'all'])->create(['title' => 'Pro news', 'slug' => 'pro-news']);

    Passport::actingAs(User::factory()->create());

    $this->getJson('/api/v1/changelog')->assertOk()->assertJsonCount(0, 'data');
    $this->getJson('/api/v1/changelog/pro-news')->assertNotFound();
    $this->getJson('/api/v1/changelog/unread-count')->assertJsonPath('data.unread', 0);
});

it('counts unread entries and marks one as read', function () {
    $first = ChangelogEntry::factory()->published()->create(['slug' => 'one']);
    ChangelogEntry::factory()->published()->create(['slug' => 'two']);

    $user = User::factory()->create();
    Passport::actingAs($user);

    $this->getJson('/api/v1/changelog/unread-count')->assertJsonPath('data.unread', 2);

    $this->postJson('/api/v1/changelog/one/read')->assertOk();
    $this->postJson('/api/v1/changelog/one/read')->assertOk();

    expect(ChangelogRead::query()->where('user_id', $user->id)->where('changelog_entry_id', $first->id)->count())->toBe(1);

    $this->getJson('/api/v1/changelog/unread-count')->assertJsonPath('data.unread', 1);
    $this->getJson('/api/v1/changelog/one')->assertOk()->assertJsonPath('data.is_read', true);
});

it('keeps read state per member', function () {
    ChangelogEntry::factory()->published()->create(['slug' => 'shared']);

    Passport::actingAs(User::factory()->create());
    $this->postJson('/api/v1/changelog/shared/read')->assertOk();

    Passport::actingAs(User::factory()->create());
    $this->getJson('/api/v1/changelog/unread-count')->assertJsonPath('data.unread', 1);
});

it('marks everything as read', function () {
    ChangelogEntry::factory()->published()->count(3)->create();

    Passport::actingAs(User::factory()->create());

    $this->postJson('/api/v1/changelog/read-all')->assertOk()->assertJsonPath('data.marked', 3);
    $this->getJson('/api/v1/changelog/unread-count')->assertJsonPath('data.unread', 0);
});

it('will not mark an entry the member cannot see', function () {
    ChangelogEntry::factory()->create(['slug' => 'secret']);

    Passport::actingAs(User::factory()->create());

    $this->postJson('/api/v1/changelog/secret/read')->assertNotFound();
});

it('offers the newest unread major entry for the popup', function () {
    ChangelogEntry::factory()->published()->create(['title' => 'Minor']);
    ChangelogEntry::factory()->published()->major()->create(['title' => 'Old major', 'slug' => 'old-major', 'published_at' => now()->subDays(3)]);
    ChangelogEntry::factory()->published()->major()->create(['title' => 'New major', 'slug' => 'new-major', 'published_at' => now()->subDay()]);

    Passport::actingAs(User::factory()->create());

    $this->getJson('/api/v1/changelog/latest-major')->assertOk()->assertJsonPath('data.entry.title', 'New major');

    $this->postJson('/api/v1/changelog/new-major/read')->assertOk();

    $this->getJson('/api/v1/changelog/latest-major')->assertJsonPath('data.entry.title', 'Old major');

    $this->postJson('/api/v1/changelog/old-major/read')->assertOk();

    $this->getJson('/api/v1/changelog/latest-major')->assertOk()->assertJsonPath('data.entry', null);
});
