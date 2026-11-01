<?php

use App\Models\Billing\Plan;
use App\Models\Billing\PlanGrant;
use App\Models\Broadcasts\Announcement;
use App\Models\Broadcasts\AnnouncementDismissal;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Laravel\Passport\Passport;

it('shows a live announcement and hides drafts, scheduled banners and ended ones', function () {
    Announcement::factory()->live()->create(['title' => 'Live']);
    Announcement::factory()->create(['title' => 'Draft']);
    Announcement::factory()->scheduled()->create(['title' => 'Scheduled']);
    Announcement::factory()->ended()->create(['title' => 'Ended']);

    Passport::actingAs(User::factory()->create());

    $this->getJson('/api/v1/announcements')
        ->assertOk()
        ->assertJsonCount(1, 'data.announcements')
        ->assertJsonPath('data.announcements.0.title', 'Live')
        ->assertJsonMissingPath('data.announcements.0.audience');
});

it('needs a signed-in member', function () {
    $this->getJson('/api/v1/announcements')->assertUnauthorized();
});

it('announces maintenance ahead of its window', function () {
    Announcement::factory()->maintenance()->create([
        'title' => 'Tonight',
        'starts_at' => now()->addHours(2),
        'ends_at' => now()->addHours(3),
    ]);

    Passport::actingAs(User::factory()->create());

    $this->getJson('/api/v1/announcements')
        ->assertOk()
        ->assertJsonPath('data.announcements.0.title', 'Tonight')
        ->assertJsonPath('data.announcements.0.window_open', false)
        ->assertJsonPath('data.maintenance_blocking_writes', false);
});

it('says when a window that blocks writes is open', function () {
    Announcement::factory()->maintenance()->create();

    Passport::actingAs(User::factory()->create());

    $this->getJson('/api/v1/announcements')
        ->assertJsonPath('data.announcements.0.window_open', true)
        ->assertJsonPath('data.maintenance_blocking_writes', true);
});

it('dismisses per member, never for anyone else', function () {
    $announcement = Announcement::factory()->live()->create();
    $ada = User::factory()->create();
    $grace = User::factory()->create();

    Passport::actingAs($ada);
    $this->postJson("/api/v1/announcements/{$announcement->id}/dismiss")->assertOk();
    $this->postJson("/api/v1/announcements/{$announcement->id}/dismiss")->assertOk();
    $this->getJson('/api/v1/announcements')->assertJsonCount(0, 'data.announcements');

    Passport::actingAs($grace);
    $this->getJson('/api/v1/announcements')->assertJsonCount(1, 'data.announcements');

    expect(AnnouncementDismissal::query()->count())->toBe(1);
});

it('refuses to dismiss what is not dismissible', function () {
    $announcement = Announcement::factory()->live()->create(['dismissible' => false]);

    Passport::actingAs(User::factory()->create());

    $this->postJson("/api/v1/announcements/{$announcement->id}/dismiss")->assertUnprocessable();
    $this->getJson('/api/v1/announcements')->assertJsonCount(1, 'data.announcements');
});

it('does not let a member dismiss an announcement meant for someone else', function () {
    $announcement = Announcement::factory()->live()->create(['audience' => ['plans' => ['pro']]]);

    Passport::actingAs(referralUser());

    $this->postJson("/api/v1/announcements/{$announcement->id}/dismiss")->assertNotFound();
    $this->postJson('/api/v1/announcements/'.fake()->uuid().'/dismiss')->assertNotFound();
});

it('aims a banner at a plan', function () {
    Plan::factory()->create(['slug' => 'free']);
    config(['billing.default_plan' => 'free']);
    $pro = Plan::factory()->create(['slug' => 'pro', 'credits_monthly' => 25000]);

    Announcement::factory()->live()->create(['title' => 'For Pro', 'audience' => ['plans' => ['pro']]]);
    Announcement::factory()->live()->create(['title' => 'For Free', 'audience' => ['plans' => ['free']]]);

    $freeUser = referralUser();
    $proUser = referralUser();
    PlanGrant::factory()->forWorkspace($proUser->ownedWorkspaces()->first())->forPlan($pro)->active()->create();

    Passport::actingAs($proUser);
    $this->getJson('/api/v1/announcements')->assertJsonCount(1, 'data.announcements')->assertJsonPath('data.announcements.0.title', 'For Pro');

    Passport::actingAs($freeUser);
    $this->getJson('/api/v1/announcements')->assertJsonCount(1, 'data.announcements')->assertJsonPath('data.announcements.0.title', 'For Free');
});

it('aims a banner at one workspace', function () {
    $inside = referralUser();
    $outside = referralUser();
    $workspace = $inside->ownedWorkspaces()->first();

    Announcement::factory()->live()->create(['audience' => ['workspace_ids' => [$workspace->id]]]);

    Passport::actingAs($inside);
    $this->getJson('/api/v1/announcements')->assertJsonCount(1, 'data.announcements');

    Passport::actingAs($outside);
    $this->getJson('/api/v1/announcements')->assertJsonCount(0, 'data.announcements');
});

it('picks up a publish straight away despite the cache', function () {
    $announcement = Announcement::factory()->create();
    Passport::actingAs(User::factory()->create());

    $this->getJson('/api/v1/announcements')->assertJsonCount(0, 'data.announcements');

    $announcement->forceFill(['published_at' => now()])->save();

    $this->getJson('/api/v1/announcements')->assertJsonCount(1, 'data.announcements');

    $announcement->forceFill(['ends_at' => now()->subSecond()])->save();

    $this->getJson('/api/v1/announcements')->assertJsonCount(0, 'data.announcements');
});

it('stops showing a cached announcement the moment it ends', function () {
    $announcement = Announcement::factory()->live()->create(['ends_at' => now()->addMinute()]);
    Passport::actingAs(User::factory()->create());

    $this->getJson('/api/v1/announcements')->assertJsonCount(1, 'data.announcements');

    $this->travel(2)->minutes();

    $this->getJson('/api/v1/announcements')->assertJsonCount(0, 'data.announcements');

    expect($announcement->fresh()->status())->toBe('ended');
});

it('serves the live list again from a warm cache that really serializes', function () {
    // The array store keeps objects as they are; production stores serialize,
    // and `cache.serializable_classes` is false, so a cached model would come
    // back as an incomplete class.
    config(['cache.default' => 'array', 'cache.stores.array.serialize' => true]);
    Cache::forgetDriver('array');

    Announcement::factory()->live()->create(['title' => 'Live']);

    Passport::actingAs(User::factory()->create());

    foreach (range(1, 3) as $attempt) {
        $this->getJson('/api/v1/announcements')
            ->assertOk()
            ->assertJsonPath('data.announcements.0.title', 'Live');
    }
});
