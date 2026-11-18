<?php

use App\Models\User;
use Laravel\Passport\Passport;

it('saves where a social signup came from', function () {
    $user = User::factory()->create();
    Passport::actingAs($user);

    $this->postJson('/api/v1/user/signup-attribution', [
        'attribution' => ['utm_source' => 'youtube', 'template' => 'sales-call-prep-brief'],
    ])->assertOk();

    expect($user->fresh()->signup_attribution)->toBe(['utm_source' => 'youtube', 'template' => 'sales-call-prep-brief']);
});

it('keeps the first attribution and never rewrites it', function () {
    $user = User::factory()->create(['signup_attribution' => ['utm_source' => 'newsletter']]);
    Passport::actingAs($user);

    $this->postJson('/api/v1/user/signup-attribution', [
        'attribution' => ['utm_source' => 'google'],
    ])->assertOk();

    expect($user->fresh()->signup_attribution)->toBe(['utm_source' => 'newsletter']);
});

it('does not attribute an account that signs in again later', function () {
    $user = User::factory()->create(['created_at' => now()->subDays(3)]);
    Passport::actingAs($user);

    $this->postJson('/api/v1/user/signup-attribution', [
        'attribution' => ['utm_source' => 'google'],
    ])->assertOk();

    expect($user->fresh()->signup_attribution)->toBeNull();
});

it('requires authentication to save attribution', function () {
    $this->postJson('/api/v1/user/signup-attribution', ['attribution' => []])->assertUnauthorized();
});
