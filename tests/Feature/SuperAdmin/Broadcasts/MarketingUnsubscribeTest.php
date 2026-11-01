<?php

use App\Models\User;
use App\Services\Broadcasts\MarketingPreferences;
use Laravel\Passport\Passport;

it('shows the unsubscribe page without changing anything, then unsubscribes on confirm', function () {
    $user = User::factory()->create();
    $url = app(MarketingPreferences::class)->pageUrl($user);

    $this->get($url)->assertOk()->assertSee('Unsubscribe from marketing emails?');
    expect($user->fresh()->hasOptedOutOfMarketing())->toBeFalse();

    $this->post($url)->assertOk()->assertSee('You are unsubscribed');
    expect($user->fresh()->hasOptedOutOfMarketing())->toBeTrue();

    $this->get($url)->assertOk()->assertSee('You are unsubscribed');
});

it('unsubscribes in one click from a mail client, with no session and no token', function () {
    $user = User::factory()->create();

    $this->post(app(MarketingPreferences::class)->oneClickUrl($user), ['List-Unsubscribe' => 'One-Click'])->assertOk();

    expect($user->fresh()->hasOptedOutOfMarketing())->toBeTrue();
});

it('rejects an unsigned or tampered link', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $this->post("/api/v1/broadcasts/unsubscribe/{$user->id}")->assertForbidden();
    $this->get("/broadcasts/unsubscribe/{$user->id}")->assertForbidden();

    $tampered = str_replace($user->id, $other->id, app(MarketingPreferences::class)->oneClickUrl($user));
    $this->post($tampered)->assertForbidden();

    expect($user->fresh()->hasOptedOutOfMarketing())->toBeFalse()->and($other->fresh()->hasOptedOutOfMarketing())->toBeFalse();
});

it('lets a member read and change the preference from the app', function () {
    $user = User::factory()->create();
    Passport::actingAs($user);

    $this->getJson('/api/v1/communication-preferences')->assertOk()->assertJsonPath('data.marketing_emails', true);

    $this->patchJson('/api/v1/communication-preferences', ['marketing_emails' => false])
        ->assertOk()
        ->assertJsonPath('data.marketing_emails', false);
    expect($user->fresh()->marketing_opted_out_at)->not->toBeNull();

    $this->patchJson('/api/v1/communication-preferences', ['marketing_emails' => true])
        ->assertJsonPath('data.marketing_emails', true);
    expect($user->fresh()->marketing_opted_out_at)->toBeNull();

    $this->patchJson('/api/v1/communication-preferences', [])->assertUnprocessable();
});

it('keeps the opted-out time when unsubscribing twice', function () {
    $user = User::factory()->create(['marketing_opted_out_at' => now()->subDay()]);
    $stamp = $user->marketing_opted_out_at->toIso8601String();

    app(MarketingPreferences::class)->optOut($user);

    expect($user->fresh()->marketing_opted_out_at->toIso8601String())->toBe($stamp);
});
