<?php

use App\Models\Broadcasts\Announcement;
use Illuminate\Support\Facades\Cache;
use Laravel\Passport\Passport;

it('turns away a write while a blocking window is open, with Retry-After', function () {
    Announcement::factory()->maintenance()->create(['title' => 'Database upgrade']);

    Passport::actingAs(referralUser());

    $response = $this->postJson('/api/v1/workspaces', ['name' => 'Another'])->assertStatus(503);

    expect($response->headers->get('Retry-After'))->not->toBeNull()
        ->and($response->json('message'))->toContain('Database upgrade');
});

it('still lets members read during the window', function () {
    Announcement::factory()->maintenance()->create();

    Passport::actingAs(referralUser());

    $this->getJson('/api/v1/workspaces')->assertOk();
    $this->getJson('/api/v1/announcements')->assertOk();
});

it('lets the exempt paths through: sign-in, dismissing a banner and preferences', function () {
    Announcement::factory()->maintenance()->create(['dismissible' => true]);
    $user = referralUser();

    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])->assertSuccessful();

    Passport::actingAs($user);

    $this->patchJson('/api/v1/communication-preferences', ['marketing_emails' => false])->assertOk();
});

it('does nothing before the window opens, after it closes, or when the notice does not block', function () {
    Announcement::factory()->maintenance()->create(['starts_at' => now()->addHour(), 'ends_at' => now()->addHours(2)]);
    Announcement::factory()->maintenance(blocksWrites: false)->create();
    Announcement::factory()->maintenance()->create(['starts_at' => now()->subHours(3), 'ends_at' => now()->subHours(2)]);
    Announcement::factory()->maintenance()->create(['published_at' => null]);

    Passport::actingAs(referralUser());

    $this->postJson('/api/v1/workspaces', ['name' => 'Another'])->assertCreated();
});

it('never blocks the Super Admin API, so an operator can always end the window', function () {
    $announcement = Announcement::factory()->maintenance()->create();
    $headers = superAdminHeaders(superAdmin());

    $this->withHeaders($headers)
        ->postJson(saUrl("broadcasts/announcements/{$announcement->id}/unpublish"))
        ->assertOk();

    Passport::actingAs(referralUser());

    $this->postJson('/api/v1/workspaces', ['name' => 'Another'])->assertCreated();
});

it('keeps turning writes away once the window is cached', function () {
    config(['cache.default' => 'array', 'cache.stores.array.serialize' => true]);
    Cache::forgetDriver('array');

    Announcement::factory()->maintenance()->create(['title' => 'Database upgrade']);

    Passport::actingAs(referralUser());

    foreach (range(1, 3) as $attempt) {
        $this->postJson('/api/v1/workspaces', ['name' => 'Another'])->assertStatus(503);
    }
});
